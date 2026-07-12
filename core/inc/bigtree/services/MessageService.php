<?php
	namespace BigTree\Services;

	use BigTree\Api\Entity;
	use BigTree\Api\Pagination;
	use BigTree\Api\Request;
	use BigTree\Api\Response;
	use BigTree\Api\Exceptions\BadRequestException;
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

			if ($folder === "sent") {
				$where = "sender = ?";
				$args = [$me];
			} elseif ($folder === "in") {
				$where = "recipients LIKE ?";
				$args = ["%|$me|%"];
			} else {
				throw new BadRequestException("folder must be in or sent", "bad_folder");
			}

			return Pagination::paginate(
				$request,
				"SELECT COUNT(*) FROM bigtree_messages WHERE " . $where,
				"SELECT * FROM bigtree_messages WHERE " . $where . " ORDER BY date DESC",
				$args,
				function ($r, $names) {

					return $this->present($r, $names);
				},
				100,
				function ($rows) {

					return $this->nameMap($rows);
				}
			);
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
			$id = $request->id();
			$message = $this->loadAccessible($id, $request->user);

			return Response::ok($this->present($message, $this->nameMap([$message])));
		}

		/**
		 * One batched lookup of every sender/recipient name across the given
		 * message rows, keyed by user id. Deleted users simply drop out of the
		 * map (presented as null names).
		 */
		private function nameMap(array $rows): array {
			$ids = [];

			foreach ($rows as $r) {
				$ids[] = (int)$r["sender"];

				foreach (explode("|", $r["recipients"] ?? "") as $part) {
					if ($part !== "") {
						$ids[] = (int)$part;
					}
				}
			}

			$ids = array_values(array_unique(array_filter($ids)));

			if (!$ids) {
				return [];
			}

			$placeholders = \BigTree\Api\Sanitize::placeholders($ids);
			$users = SQL::fetchAll("SELECT id, name FROM bigtree_users WHERE id IN ($placeholders)", ...$ids);
			$map = [];

			foreach ($users as $u) {
				$map[(int)$u["id"]] = $u["name"];
			}

			return $map;
		}

		public function create(Request $request) {
			$d = $request->body;
			$subject = BigTree::safeEncode(strip_tags((string)($d["subject"] ?? "")));
			$message = strip_tags((string)($d["message"] ?? ""), "<p><b><strong><em><i><a>");
			$message = preg_replace('/href="javascript:[^"]+"/', '', $message);
			$message = str_replace(['href=javascript:', 'onclick='], '', $message);
			$recipients = array_map("intval", (array)($d["recipients"] ?? []));

			if (!$recipients) {
				throw new BadRequestException("recipients required", "missing_recipients");
			}

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

			$row = SQL::fetch("SELECT * FROM bigtree_messages WHERE id = ?", $id);

			return Response::created($this->present($row, $this->nameMap([$row])), null);
		}

		public function markRead(Request $request) {
			$id = $request->id();
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
			$row = Entity::findOrFail("bigtree_messages", $id, "Message");
			$me = (int)$user->id;

			if ((int)$row["sender"] !== $me && strpos($row["recipients"] ?? "", "|$me|") === false) {
				throw new AuthorizationException("Not your message");
			}

			return $row;
		}

		private function present(array $r, array $names = []) {
			$recipients = array_values(array_filter(array_map("intval", explode("|", $r["recipients"] ?? ""))));

			return [
				"id" => (int)$r["id"],
				"sender" => (int)$r["sender"],
				"sender_name" => $names[(int)$r["sender"]] ?? null,
				"recipients" => $recipients,
				"recipient_names" => array_map(function ($id) use ($names) {

					return ["id" => $id, "name" => $names[$id] ?? null];
				}, $recipients),
				"subject" => $r["subject"],
				"message" => $r["message"],
				"response_to" => (int)$r["response_to"],
				"date" => $r["date"],
				"read_by" => array_values(array_filter(array_map("intval", explode("|", $r["read_by"] ?? "")))),
			];
		}
	}
