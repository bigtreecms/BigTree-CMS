var BigTreeAPI = (function() {
	let db;
	let initialized = false;
	const schema = {
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
	const schema_version = 1;

	async function call(options) {
		return new Promise(function(resolve, reject) {
			let endpoint, callback, context, parameters, method;

			if (typeof options !== "object") {
				reject("The call method requires an object as its first parameter.");

				return null;
			}

			if (typeof options.endpoint !== "string") {
				reject("You must pass a string endpoint property to this method.");

				return null;
			} else {
				endpoint = options.endpoint;
			}

			if (typeof options.method !== "string") {
				method = "GET";
			} else {
				method = options.method.toUpperCase();

				if (method !== "GET" && method !== "POST") {
					reject("Invalid method: valid methods are GET and POST.");

					return null;
				}
			}

			if (typeof options.parameters !== "object") {
				parameters = [];
			} else {
				parameters = options.parameters;
			}

			if (typeof options.context !== "undefined") {
				context = options.context;
			}

			$.ajax("www_root/api/" + endpoint, {
				data: parameters,
				method: method
			}).done(function(response) {
				if (response.success) {
					resolve(response.response);
				} else {
					reject("API call failed:" + response.error);
				}
			}).fail(function(xhr) {
				reject("Unknown error.");
			});
		});
	}

	async function getStoredData(table) {
		if (!initialized) {
			await init();
		}

		return new Promise(
			function(resolve, reject) {
				let transaction = db.transaction(table, "readonly");
				let store = transaction.objectStore(table);
				let request = store.getAll();


				request.onsuccess = function() {
					resolve(request.result);
				};

				request.onerror = function() {
					reject("error");
				}
			}
		);
	}

	async function init() {
		return new Promise(
			function(resolve, reject) {
				window.indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
				window.IDBTransaction = window.IDBTransaction || window.webkitIDBTransaction || window.msIDBTransaction || {READ_WRITE: "readwrite"};
				window.IDBKeyRange = window.IDBKeyRange || window.webkitIDBKeyRange || window.msIDBKeyRange;

				if (!window.indexedDB) {
					alert("BigTree is not supported in this browser. Please upgrade to a modern browser.");
				}

				let request = window.indexedDB.open("BigTree", BigTreeAPI.schema_version);
				
				request.onsuccess = function(event) {
					db = event.target.result;
					initialized = true;
					
					resolve();
				};
				
				request.onerror = function(event) {
					reject(event.target.errorCode);
				};
				
				request.onupgradeneeded = function(event) {
					db = event.target.result;

					// Remove all existing data stores
					for (let store in db.objectStoreNames) {
						if (db.objectStoreNames.contains(store)) {
							db.deleteObjectStore(store);
						}
					}

					// Create the new stores from the latest schema
					for (let store in BigTreeAPI.schema) {
						if (BigTreeAPI.schema.hasOwnProperty(store)) {
							let schema = BigTreeAPI.schema[store];
							let table = db.createObjectStore(store, { keyPath: schema.key });

							for (let x = 0; x < schema.indexes.length; x++) {
								table.createIndex(schema.indexes[x], schema.indexes[x], { unique: false });
							}

							// Get the latest data set
							table.transaction.oncomplete = function() {
								for (let store in BigTreeAPI.schema) {
									if (BigTreeAPI.schema.hasOwnProperty(store)) {
										this.call({
											endpoint: "indexed-db/" + store,
											callback: function (response, context) {
												let transaction = db.transaction(db.objectStoreNames, "readwrite");
												let store = transaction.objectStore(context);

												if (typeof response.insert !== "undefined") {
													for (let x = 0; x < response.insert.length; x++) {
														store.add(response.insert[x]);
													}
												}

												if (typeof response.update !== "undefined") {
													for (let index in response.update) {
														if (response.update.hasOwnProperty(index)) {
															store.put(response.update[index], index);
														}
													}
												}

												if (typeof response.delete !== "undefined") {
													for (let x = 0; x < response.delete.length; x++) {
														store.delete(response.delete[x]);
													}
												}

												initialized = true;
												resolve();
											},
											context: store
										});
									}
								}
							};
						}
					}
				};
			}
		);
	}

	return { call: call, getStoredData: getStoredData, schema: schema, schema_version: schema_version };

})();