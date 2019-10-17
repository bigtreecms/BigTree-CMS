var BigTreeAPI = (function() {
	let background_update_paused = false;
	let background_update_timer;
	let db;
	let initialized = false;
	let next_page = null;
	let upgrading = false;
	let watched_stores = [];

	const schema = {
		"pages": {
			"indexes": [
				"id",
				"parent",
				"in_nav",
				"position",
				"archived",
				"access_level"
			],
			"key": "id"
		},
		"settings": {
			"indexes": [
				"id",
				"title",
				"access_level"
			],
			"key": "id"
		},
		"users": {
			"indexes": [
				"id",
				"name",
				"email",
				"company",
				"level",
				"access_level"
			],
			"key": "id"
		},
		"files": {
			"indexes": [
				"id",
				"folder",
				"title",
				"type",
				"access_level"
			],
			"key": "id"
		},
		"tags": {
			"indexes": [
				"id",
				"tag",
				"usage_count",
				"access_level"
			],
			"key": "id"
		},
		"module-groups": {
			"indexes": [
				"id",
				"position"
			],
			"key": "id"
		},
		"modules": {
			"indexes": [
				"id",
				"group",
				"position",
				"access_level"
			],
			"key": "id"
		},
		"view-cache": {
			"indexes": [
				"id",
				"view",
				"entry",
				"group_field",
				"sort_field",
				"group_sort_field",
				"position",
				"access_level"
			],
			"key": "id"
		}
	};
	const schema_version = 1;

	async function backgroundUpdate() {
		if (BigTreeAPI.background_update_paused) {
			return;
		}

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
				await updateCache(store, data);
				BigTreeEventBus.$emit("api-data-changed", store);
			}

			last_updated[store] = Math.floor(Date.now() / 1000);
		}

		$.cookie("bigtree-indexeddb-last-updated", last_updated);
	}

	async function cacheDelete(store, key) {
		let request = store.delete(key);

		return new Promise((resolve, reject) => {
			request.onsuccess = resolve;
			request.onerror = reject;
		});
	}

	async function cachePut(store, entry) {
		// Make sure numeric values are stored as strings so that we can query them back consistently
		for (let index in entry) {
			if (entry.hasOwnProperty(index)) {
				if (typeof entry[index] === "number") {
					entry[index] = String(entry[index]);
				}
			}
		}

		let request = store.put(entry);

		return new Promise((resolve, reject) => {
			request.onsuccess = resolve;
			request.onerror = reject;
		});
	}

	async function call(options) {
		BigTreeAPI.background_update_paused = true;

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
				parameters = {};
			} else {
				parameters = options.parameters;
			}

			if (typeof options.context !== "undefined") {
				context = options.context;
			}

			$.ajax("www_root/api/" + endpoint, {
				data: parameters,
				method: method
			}).done(async function(response) {
				if (response.next_page) {
					BigTreeAPI.next_page = response.next_page;
				} else {
					BigTreeAPI.next_page = null;
				}

				BigTreeAPI.background_update_paused = false;

				if (response.success) {
					if (typeof response.response.cache !== "undefined") {
						for (let store in response.response.cache) {
							if (response.response.cache.hasOwnProperty(store)) {
								await BigTreeAPI.updateCache(store, response.response.cache[store]);
							}
						}
					}

					resolve(response.response);
				} else {
					reject("API call failed:" + response.error);
				}
			}).fail(function(xhr) {
				BigTreeAPI.background_update_paused = false;
				reject("Unknown error.");
			});
		});
	}

	async function getNextPage() {
		if (!BigTreeAPI.next_page) {
			console.log("API response does not have another page.");

			return;
		}

		return new Promise((resolve, reject) => {
			$.ajax(BigTreeAPI.next_page).done(function(response) {
				if (response.next_page) {
					BigTreeAPI.next_page = response.next_page;
				} else {
					BigTreeAPI.next_page = null;
				}

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

					background_update_timer = setInterval(backgroundUpdate, 30000);
					initialized = true;
					resolve();
				} else {
					let resolution_timer = setInterval(() => {
						if (!upgrading) {
							if (background_update_timer) {
								clearInterval(background_update_timer);
							}

							background_update_timer = setInterval(backgroundUpdate, 30000);
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

				BigTree.toggle_busy("Updating data (this may take a while)");

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
									let response = await BigTreeAPI.call({ endpoint: "indexed-db/" + store });
									await updateCache(store, response);
									last_updated[store] = Math.floor(Date.now() / 1000);
								}
							}

							$.cookie.json = true;
							$.cookie("bigtree-indexeddb-last-updated", last_updated);

							initialized = true;
							upgrading = false;

							BigTree.toggle_busy();
							resolve();
						};
					}
				}
			};
		});
	}

	async function updateCache(store, data) {
		if (!initialized) {
			await init();
		}

		return new Promise(async (resolve, reject) => {
			let transaction = db.transaction(db.objectStoreNames, "readwrite");
			let transaction_store = transaction.objectStore(store);

			if (typeof data.put !== "undefined") {
				for (let index in data.put) {
					if (data.put.hasOwnProperty(index)) {
						await cachePut(transaction_store, data.put[index]);
					}
				}
			}

			if (typeof data.delete !== "undefined") {
				for (let x = 0; x < data.delete.length; x++) {
					await cacheDelete(transaction_store, data.delete[x]);
				}
			}

			if (BigTreeAPI.next_page) {
				let response = await BigTreeAPI.getNextPage();
				await updateCache(store, response);
			}

			resolve();
		});
	}

	// Updates locally cached data while awaiting an update from the API
	// Changes must be an object with the key as the unique ID and key => value stores for updated data
	async function updateCacheByID(store, changes) {
		return new Promise(async (resolve, reject) => {
			let updates = [];

			for (let key in changes) {
				if (changes.hasOwnProperty(key)) {
					let data = await getStoredDataMatching(store, "id", key);

					if (data.length) {
						let item = data[0];

						for (let change_key in changes[key]) {
							if (changes[key].hasOwnProperty(change_key)) {
								item[change_key] = changes[key][change_key];
							}
						}

						updates.push(item);
					}
				}
			}

			await updateCache(store, { put: updates });
			resolve();
		});
	}

	return {
		background_update_paused: background_update_paused,
		call: call,
		getStoredData: getStoredData,
		getStoredDataMatching: getStoredDataMatching,
		getNextPage: getNextPage,
		schema: schema,
		schema_version: schema_version,
		updateCache: updateCache,
		updateCacheByID: updateCacheByID
	};

})();