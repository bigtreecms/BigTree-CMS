<?php
	namespace BigTree\Services;

	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
	use BigTree\Api\Exceptions\NotFoundException;
	use BigTree\Api\Exceptions\AuthorizationException;
	use BigTree;
	use SQL;

	/**
	 * Inbox. Recipients are stored pipe-delimited in bigtree_messages.recipients
	 * (e.g. "|3|7|12|"). Access is allowed if the user is sender OR appears in recipients.
	 */
	class MessageService {
		public function list(Request $request) {
			$folder = $request->query["folder"] ?? "in";
			$me = (int)$request->user->id;
			$p = Pagination::offset($request, 100);

			$where = "";
			$args = [];
			if ($folder === "sent") {
				$where = "sender = ?";
				$args = [$me];
			} elseif ($folder === "in") {
				$where = "recipients LIKE ?";
				$args = ["%|$me|%"];
			} else {
				throw new BadRequestException("folder must be in or sent", "bad_folder", 400);
			}

			$total = (int)SQL::fetchSingle(...array_merge(["SELECT COUNT(*) FROM bigtree_messages WHERE " . $where], $args));
			$rows = SQL::fetchAll(...array_merge([
				"SELECT * FROM bigtree_messages WHERE " . $where . " ORDER BY date DESC LIMIT " . (int)$p["limit"] . " OFFSET " . (int)$p["offset"],
			], $args));

			return Response::ok(array_map([$this, "present"], $rows), Pagination::offsetMeta($p["page"], $p["per_page"], $total));
		}

		public function unreadCount(Request $request) {
			$me = (int)$request->user->id;
			$count = (int)SQL::fetchSingle(
				"SELECT COUNT(*) FROM bigtree_messages WHERE recipients LIKE ? AND (read_by NOT LIKE ? OR read_by IS NULL)",
				"%|$me|%", "%|$me|%"
			);
			return Response::ok(["unread" => $count]);
		}

		public function get(Request $request) {
			$id = (int)$request->route_params["id"];
			$message = $this->loadAccessible($id, $request->user);
			return Response::ok($this->present($message));
		}

		public function create(Request $request) {
			$d = $request->body;
			$subject = htmlspecialchars(strip_tags((string)($d["subject"] ?? "")));
			$message = strip_tags((string)($d["message"] ?? ""), "<p><b><strong><em><i><a>");
			$message = preg_replace('/href="javascript:[^"]+"/', '', $message);
			$message = str_replace(['href=javascript:', 'onclick='], '', $message);
			$recipients = array_map("intval", (array)($d["recipients"] ?? []));
			if (!$recipients) throw new BadRequestException("recipients required", "missing_recipients", 400);

			$send_to = "|" . implode("|", array_filter($recipients)) . "|";
			$in_response_to = (int)($d["in_response_to"] ?? 0);

			$id = (int)SQL::insert("bigtree_messages", [
				"sender" => $request->user->id,
				"recipients" => $send_to,
				"subject" => $subject,
				"message" => $message,
				"date" => "NOW()",
				"response_to" => $in_response_to,
			]);
			return Response::created($this->present(SQL::fetch("SELECT * FROM bigtree_messages WHERE id = ?", $id)), null);
		}

		public function markRead(Request $request) {
			$id = (int)$request->route_params["id"];
			$message = $this->loadAccessible($id, $request->user);
			$me = (int)$request->user->id;
			$read_by = $message["read_by"] ?? "";
			if (strpos($read_by, "|$me|") === false) {
				$read_by = ($read_by ?: "|") . "$me|";
				SQL::update("bigtree_messages", $id, ["read_by" => $read_by]);
			}
			return Response::noContent();
		}

		// — helpers —

		private function loadAccessible($id, $user) {
			$row = SQL::fetch("SELECT * FROM bigtree_messages WHERE id = ?", $id);
			if (!$row) throw new NotFoundException("Message $id not found", "resource_not_found", 404);
			$me = (int)$user->id;
			if ((int)$row["sender"] !== $me && strpos($row["recipients"] ?? "", "|$me|") === false) {
				throw new AuthorizationException("Not your message", "permission_denied", 403);
			}
			return $row;
		}

		private function present(array $r) {
			return [
				"id" => (int)$r["id"],
				"sender" => (int)$r["sender"],
				"recipients" => array_values(array_filter(array_map("intval", explode("|", $r["recipients"] ?? "")))),
				"subject" => $r["subject"],
				"message" => $r["message"],
				"response_to" => (int)$r["response_to"],
				"date" => $r["date"],
				"read_by" => array_values(array_filter(array_map("intval", explode("|", $r["read_by"] ?? "")))),
			];
		}
	}
