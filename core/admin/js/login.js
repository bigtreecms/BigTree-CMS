var BigTreeLogin = (function() {
	var db;
	var request;

	window.indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
	window.IDBTransaction = window.IDBTransaction || window.webkitIDBTransaction || window.msIDBTransaction || {READ_WRITE: "readwrite"};
	window.IDBKeyRange = window.IDBKeyRange || window.webkitIDBKeyRange || window.msIDBKeyRange;

	function init() {
		if (!window.indexedDB) {
			alert("BigTree is not supported in this browser. Please upgrade to a modern browser.");
		}

		request = window.indexedDB.open("BigTree", 1);
		request.onsuccess = onOpenSuccess;
		request.onerror = onOpenError;
	}

	function onDbError(event) {
		alert("DB error" + event.target.errorCode);
	}

	function onOpenError(event) {
		alert("Failed to open database: " + request.errorCode);
	}

	function onOpenSuccess(event) {
		db = event.target.result;
		db.onerror = onDbError;

		// Make a request to get the latest schema
		BigTreeAPI.call({ endpoint: "indexed-db/schema", callback: schemaReturned });
	}

	function schemaReturned(response) {
		if (response.status === "new") {
			for (var i in response.schema) {
				if (response.schema.hasOwnProperty(i)) {
					if (!db.objectStoreNames.contains(i)) {
						db.deleteObjectStore(i);
					}

					schema = response.schema[i];
					db.createObjectStore(i, { keyPath: schema.key });

					for (var x = 0; x < schema.indexes.length; x++) {
						db.createIndex(schema.indexes[x], schema.indexes[x], { unique: false });
					}
				}
			}

			console.log("done");
		}
	}

	return { init: init };
})();

var BigTreeAPI = (function() {

	function call(options) {
		var endpoint, callback, parameters, method;

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

			if (method != "GET" && method != "POST") {
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

		$.ajax("www_root/api/" + endpoint, {
			data: parameters,
			method: method
		}).done(function(response) {
			if (response.success) {
				callback(response.response);
			} else {
				alert("API call failed:" + response.error);
			}
		}).fail(function(xhr, text) {
			alert("Request failed: " + text);
		});
	}

	return { call: call }
	
})();