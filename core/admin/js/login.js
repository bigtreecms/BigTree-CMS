var BigTreeLogin = (function() {
	var db;
	var request;

	window.indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
	window.IDBTransaction = window.IDBTransaction || window.webkitIDBTransaction || window.msIDBTransaction || {READ_WRITE: "readwrite"};
	window.IDBKeyRange = window.IDBKeyRange || window.webkitIDBKeyRange || window.msIDBKeyRange;

	function handleAPIResponse(response, context) {
		var transaction = db.transaction(db.objectStoreNames, "readwrite");
		var store = transaction.objectStore(context);

		if (typeof response.insert !== "undefined") {
			for (var x = 0; x < response.insert.length; x++) {
				store.add(response.insert[x]);
			}
		}

		if (typeof response.update !== "undefined") {
			for (var index in response.update) {
				if (response.update.hasOwnProperty(index)) {
					store.put(response.update[index], index);
				}
			}
		}

		if (typeof response.delete !== "undefined") {
			for (x = 0; x < response.delete.length; x++) {
				store.delete(response.delete[x]);
			}
		}
	}

	function init() {
		if (!window.indexedDB) {
			alert("BigTree is not supported in this browser. Please upgrade to a modern browser.");
		}

		request = window.indexedDB.open("BigTree", BigTreeAPI.schema_version);
		request.onsuccess = onOpenSuccess;
		request.onerror = onOpenError;
		request.onupgradeneeded = onUpgradeNeeded;
	}

	function onDbError(event) {
		alert("DB error" + event.target.errorCode);
	}

	function onOpenError(event) {
		alert("Failed to open database: " + event.errorCode);
	}

	function onOpenSuccess(event) {
		db = event.target.result;
		db.onerror = onDbError;
	}

	function onUpgradeNeeded(event) {
		db = event.target.result;

		// Remove all existing data stores
		for (var store in db.objectStoreNames) {
			if (db.objectStoreNames.contains(store)) {
				db.deleteObjectStore(store);
			}
		}

		// Create the new stores from the latest schema
		for (store in BigTreeAPI.schema) {
			if (BigTreeAPI.schema.hasOwnProperty(store)) {
				var schema = BigTreeAPI.schema[store];
				var table = db.createObjectStore(store, { keyPath: schema.key });

				for (var x = 0; x < schema.indexes.length; x++) {
					table.createIndex(schema.indexes[x], schema.indexes[x], { unique: false });
				}

				// Get the latest data set
				table.transaction.oncomplete = rebuildTables;
			}
		}
	}

	function rebuildTables() {
		for (var store in BigTreeAPI.schema) {
			BigTreeAPI.call({
				endpoint: "indexed-db/" + store,
				callback: handleAPIResponse,
				context: store
			});
		}
	}

	return { init: init };
})();

var BigTreeAPI = (function() {
	var schema = {
		"pages": {
			"indexes": [
				"parent",
				"in_nav",
				"position",
				"archived"
			],
			"key": "id"
		},
		"settings": {
			"indexes": [
				"title"
			],
			"key": "id"
		},
		"users": {
			"indexes": [
				"name",
				"email",
				"company",
				"level"
			],
			"key": "id"
		},
		"files": {
			"indexes": [
				"folder",
				"title",
				"type"
			],
			"key": "id"
		},
		"tags": {
			"indexes": [
				"tag",
				"usage_count"
			],
			"key": "id"
		},
		"module-groups": {
			"indexes": [
				"position"
			],
			"key": "id"
		},
		"modules": {
			"indexes": [
				"group",
				"position"
			],
			"key": "id"
		},
		"view-cache": {
			"indexes": [
				"view",
				"id",
				"group_field",
				"sort_field",
				"group_sort_field",
				"position"
			],
			"key": "key"
		}
	};
	var schema_version = 1;

	function call(options) {
		var endpoint, callback, context, parameters, method;

		if (typeof options !== "object") {
			alert("The call method requires an object as its first parameter.");
		}

		if (typeof options.endpoint !== "string") {
			alert("You must pass a string endpoint property to this method.");

			return null;
		} else {
			endpoint = options.endpoint;
		}

		if (typeof options.method !== "string") {
			method = "GET";
		} else {
			method = options.method.toUpperCase();

			if (method !== "GET" && method !== "POST") {
				alert("Invalid method: valid methods are GET and POST.");

				return null;
			}
		}

		if (typeof options.parameters !== "object") {
			parameters = [];
		} else {
			parameters = options.parameters;
		}

		if (typeof options.callback === "function") {
			callback = options.callback;
		}

		if (typeof options.context !== "undefined") {
			context = options.context;
		}

		$.ajax("www_root/api/" + endpoint, {
			data: parameters,
			method: method
		}).done(function(response) {
			if (response.success) {
				callback(response.response, context);
			} else {
				alert("API call failed:" + response.error);
			}
		}).fail(function(xhr) {
			console.log(xhr.responseText);
		});
	}

	return { call: call, schema: schema, schema_version: schema_version }
	
})();