var BigTreeAPI = (function() {
	let background_update_timer;
	let db;
	let initialized = false;
	let upgrading = false;
	let watched_stores = [];

	const schema = {
		"pages": {
			"indexes": [
				"id",
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

	async function background_update() {
		// Auto parse JSON
		$.cookie.json = true;

		let last_updated = $.cookie("bigtree-indexeddb-last-updated");

		if (typeof last_updated !== "object") {
			last_updated = {};
		}

		for (let x = 0; x < watched_stores.length; x++) {
			let store = watched_stores[x];
			let data = await BigTreeAPI.call({
				endpoint: "indexed-db/" + store,
				parameters: {
					since: (typeof last_updated[store] !== "undefined") ? last_updated[store] : null
				}
			});

			if (Object.keys(data).length) {
				let transaction = db.transaction(db.objectStoreNames, "readwrite");
				let transaction_store = transaction.objectStore(store);

				await update_cache(transaction_store, data);
				BigTreeEventBus.$emit("api-data-changed", store);
			}

			last_updated[store] = Math.floor(Date.now() / 1000);
		}

		$.cookie("bigtree-indexeddb-last-updated", last_updated);
	}

	async function cache_add(store, entry) {
		let request = store.add(entry);

		return new Promise((resolve, reject) => {
			request.onsuccess = resolve;
			request.onerror = reject;
		});
	}

	async function cache_delete(store, key) {
		let request = store.delete(key);

		return new Promise((resolve, reject) => {
			request.onsuccess = resolve;
			request.onerror = reject;
		});
	}

	async function cache_update(store, entry) {
		let request = store.put(entry);

		return new Promise((resolve, reject) => {
			request.onsuccess = resolve;
			request.onerror = reject;
		});
	}

	async function call(options) {
		return new Promise((resolve, reject) => {
			let endpoint, context, parameters, method;

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

	async function getStoredData(table, index, reversed) {
		if (!initialized) {
			await init();
		}

		if (watched_stores.indexOf(table) < 0) {
			watched_stores.push(table);
		}

		return new Promise((resolve, reject) => {
			let transaction = db.transaction(table, "readonly");
			let store = transaction.objectStore(table);

			if (typeof index !== "undefined") {
				let store_index = store.index(index);
				let cursor = store_index.openCursor();
				let results = [];

				cursor.onsuccess = function(event) {
					let cursor = event.target.result;

					if (cursor) {
						results.push(cursor.value);
						cursor.continue();
					} else {
						if (reversed) {
							results.reverse();
						}

						resolve(results);
					}
				};

				cursor.onerror = function() {
					reject("error");
				};
			} else {
				let request = store.getAll();

				request.onsuccess = function() {
					resolve(request.result);
				};

				request.onerror = function() {
					reject("error");
				}
			}
		});
	}

	async function getStoredDataMatching(table, index, value, reversed) {
		if (!initialized) {
			await init();
		}

		if (watched_stores.indexOf(table) < 0) {
			watched_stores.push(table);
		}

		return new Promise((resolve, reject) => {
			let transaction = db.transaction(table, "readonly");
			let store = transaction.objectStore(table);
			let store_index = store.index(index);
			let key_range = IDBKeyRange.only(String(value));
			let cursor = store_index.openCursor(key_range);
			let results = [];

			cursor.onsuccess = function(event) {
				let cursor = event.target.result;

				if (cursor) {
					results.push(cursor.value);
					cursor.continue();
				} else {
					if (reversed) {
						results.reverse();
					}

					resolve(results);
				}
			};

			cursor.onerror = function() {
				reject("error");
			}
		});
	}

	async function init() {
		return new Promise((resolve, reject) => {
			window.indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
			window.IDBTransaction = window.IDBTransaction || window.webkitIDBTransaction || window.msIDBTransaction || {READ_WRITE: "readwrite"};
			window.IDBKeyRange = window.IDBKeyRange || window.webkitIDBKeyRange || window.msIDBKeyRange;

			if (!window.indexedDB) {
				alert("BigTree is not supported in this browser. Please upgrade to a modern browser.");
			}

			let request = window.indexedDB.open("BigTree", BigTreeAPI.schema_version);

			request.onsuccess = function(event) {
				db = event.target.result;

				if (!upgrading) {
					if (background_update_timer) {
						clearInterval(background_update_timer);
					}

					background_update_timer = setInterval(background_update, 30000);
					initialized = true;
					resolve();
				} else {
					let resolution_timer = setInterval(() => {
						if (!upgrading) {
							if (background_update_timer) {
								clearInterval(background_update_timer);
							}

							background_update_timer = setInterval(background_update, 30000);
							initialized = true;
							resolve();
							clearInterval(resolution_timer);
						}
					}, 100);
				}
			};

			request.onerror = function(event) {
				reject(event.target.errorCode);
			};

			request.onupgradeneeded = function(event) {
				upgrading = true;
				db = event.target.result;

				// Remove all existing data stores
				for (let store in db.objectStoreNames) {
					if (db.objectStoreNames.hasOwnProperty(store)) {
						db.deleteObjectStore(store);
					}
				}

				let last_updated = {};

				// Create the new stores from the latest schema
				for (let store in BigTreeAPI.schema) {
					if (BigTreeAPI.schema.hasOwnProperty(store)) {
						let schema = BigTreeAPI.schema[store];
						let table = db.createObjectStore(store, { keyPath: schema.key });

						for (let x = 0; x < schema.indexes.length; x++) {
							table.createIndex(schema.indexes[x], schema.indexes[x], { unique: false });
						}

						// Get the latest data set
						table.transaction.oncomplete = async function() {
							for (let store in BigTreeAPI.schema) {
								if (BigTreeAPI.schema.hasOwnProperty(store)) {
									let data = await BigTreeAPI.call({ endpoint: "indexed-db/" + store });
									let transaction = db.transaction(db.objectStoreNames, "readwrite");
									let transaction_store = transaction.objectStore(store);

									await update_cache(transaction_store, data);
									last_updated[store] = Math.floor(Date.now() / 1000);
								}
							}

							$.cookie.json = true;
							$.cookie("bigtree-indexeddb-last-updated", last_updated);

							initialized = true;
							upgrading = false;
							resolve();
						};
					}
				}
			};
		});
	}

	async function update_cache(transaction_store, data) {
		if (typeof data.insert !== "undefined") {
			for (let x = 0; x < data.insert.length; x++) {
				await cache_add(transaction_store, data.insert[x]);
			}
		}

		if (typeof data.update !== "undefined") {
			for (let index in data.update) {
				if (data.update.hasOwnProperty(index)) {
					await cache_update(transaction_store, data.update[index]);
				}
			}
		}

		if (typeof data.delete !== "undefined") {
			for (let x = 0; x < data.delete.length; x++) {
				await cache_delete(transaction_store, data.delete[x]);
			}
		}
	}

	return {
		call: call,
		getStoredData: getStoredData,
		getStoredDataMatching: getStoredDataMatching,
		schema: schema,
		schema_version: schema_version
	};

})();