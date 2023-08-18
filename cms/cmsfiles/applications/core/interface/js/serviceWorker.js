/**
 * Invision Community
 * (c) Invision Power Services, Inc. - https://www.invisioncommunity.com
 *
 * Invision Community service worker
 */

// ------------------------------------------------
// Install/activate SW events
// ------------------------------------------------
self.addEventListener("install", (e) => {
	console.log("Service worker installed");
	e.waitUntil(
		CACHED_ASSETS.length
			? cacheAssets().then(() => {
					return self.skipWaiting();
			  })
			: self.skipWaiting()
	);
});

self.addEventListener("activate", (e) => {
	const cacheAllowList = [CACHE_NAME];

	// Clean up any caches that don't match our current cache key
	// Ensure we don't have outdated styles/assets/etc.
	e.waitUntil(
		Promise.all([
			caches.keys().then((cacheNames) => {
				return Promise.all(
					cacheNames.map((cacheName) => {
						if (cacheAllowList.indexOf(cacheName) === -1) {
							return caches.delete(cacheName);
						}
					})
				);
			}),
			self.clients.claim(),
		])
	);
});

const returnDefaultNotification = () => {
	return self.registration.showNotification(DEFAULT_NOTIFICATION_TITLE, {
		body: DEFAULT_NOTIFICATION_BODY,
		icon: NOTIFICATION_ICON,
		data: {
			url: BASE_URL,
		},
	});
};

// ------------------------------------------------
// Push notification event handler
// ------------------------------------------------
self.addEventListener("push", (e) => {
	// A couple of basic sanity checks
	if (!e.data) {
		console.log("Invalid notification data");
		return; // Invalid notification data
	}

	const pingData = e.data.json();
	const { id } = pingData;

	// We don't send the notification data in the push, otherwise we run into issues whereby
	// a user could have logged out but will still receive notification unintentionally.
	// Instead, we'll receive an ID in the push, then we'll ping the server to get
	// the actual content (and run our usual authorization checks)

	const promiseChain = fetch(`${BASE_URL}index.php?app=core&module=system&controller=notifications&do=fetchNotification&id=${id}`, {
		method: "POST",
		credentials: "include", // Must send cookies so we can check auth
	})
		.then((response) => {
			// Fetch went wrong - but we must show a notification, so just send a generic message
			if (!response.ok) {
				throw new Error("Invalid response");
			}

			return response.json();
		})
		.then((data) => {
			// The server returned an error - but we must show a notification, so just send a generic message
			if (data.error) {
				throw new Error("Server error");
			}

			const { body, url, grouped, groupedTitle, groupedUrl, icon, image } = data;
			let { title } = data;
			let tag;

			if (data.tag) {
				tag = data.tag.substr(0, 30);
			}

			let options = {
				body,
				icon: icon ? icon : NOTIFICATION_ICON,
				image: image ? image : null,
				data: {
					url,
				},
			};

			if (!tag || !grouped) {
				// This notification has no tag or grouped lang, so just send it
				// as a one-off thing
				return self.registration.showNotification(title, options);
			} else {
				return self.registration.getNotifications({ tag }).then((notifications) => {
					// Tagged notifications require some additional data
					options = {
						...options,
						tag,
						renotify: true, // Required, otherwise browsers won't renotify for this tag
						data: {
							...options.data,
							unseenCount: 1,
						},
					};

					if (notifications.length) {
						try {
							// Get the most recent notification and see if it has a count
							// If it does, increase the unseenCount by one and update the message
							// With this approach we'll always have a reference to the previous notification's count
							// and can simply increase and fire a new notification to tell the user
							const lastWithTag = notifications[notifications.length - 1];

							if (lastWithTag.data && typeof lastWithTag.data.unseenCount !== "undefined") {
								const unseenCount = lastWithTag.data.unseenCount + 1;

								options.data.unseenCount = unseenCount;
								options.body = pluralize(grouped.replace("{count}", unseenCount), unseenCount);

								if (groupedUrl) {
									options.data.url = groupedUrl ? groupedUrl : options.data.url;
								}

								if (groupedTitle) {
									title = pluralize(groupedTitle.replace("{count}", unseenCount), unseenCount);
								}

								lastWithTag.close();
							}
						} catch (err) {
							console.log(err);
						}
					}

					return self.registration.showNotification(title, options);
				});
			}
		})
		.catch((err) => {
			// The server returned an error - but we must show a notification, so just send a generic message
			return returnDefaultNotification();
		});

	e.waitUntil(promiseChain);
});

// ------------------------------------------------
// Notification click event handler
// ------------------------------------------------
self.addEventListener("notificationclick", (e) => {
	const { data } = e.notification;

	e.waitUntil(
		self.clients.matchAll().then((clients) => {
			console.log(clients);

			// If we already have the site open, use that window
			if (clients.length > 0 && "navigate" in clients[0]) {
				if (data.url) {
					clients[0].navigate(data.url);
				} else {
					clients[0].navigate(BASE_URL);
				}

				return clients[0].focus();
			}

			// otherwise open a new window
			return self.clients.openWindow(data.url ? data.url : BASE_URL);
		})
	);
});

// ------------------------------------------------
// Fetch a resource - use to detect offline state
// ------------------------------------------------
self.addEventListener("fetch", (e) => {
	const { request } = e;

	// We test /admin/ first and then a fallback for the deprecated custom admin directory name feature
	if (request.url.startsWith(BASE_URL + "admin/") || e.currentTarget.location.href.match(/type=admin/)) {
		log("In ACP, nothing to do...");
		return;
	}

	if (!request.url.startsWith(BASE_URL) || (request.method === "GET" && request.mode !== "navigate")) {
		return;
	}

	// We intercept fetch requests in the following situations:
	// 1: GET requests where we're offline
	//    	Show a cached offline page instead
	// 2: POST requests when we're a guest
	// 		We need to wrap POST requests in an additional call to get the correct csrf token first

	// Situation 1: offline GET requests
	if (request.mode === "navigate" && request.method === "GET" && !navigator.onLine) {
		e.respondWith(
			fetch(request).catch((err) => {
				return caches.open(CACHE_NAME).then((cache) => {
					console.log(`Browser appears to be offline: ${request.url}`);
					return cache.match(OFFLINE_URL);
				});
			})
		);
		return;
	}

	let matches = e.currentTarget.location.href.match(/loggedIn=(true|false)/);
	const loggedIn = matches[1];

	// If we're logged in, we need none of this
	if (loggedIn == 'true') {
		log("Logged in, nothing to do...");
		return;
	}

	e.respondWith(
		new Promise((resolve, reject) => {
			log(`On navigation, logged_in is ${loggedIn}`);

			// Situation 2: POST requests when we're a guest
			if (loggedIn == 'false' && request.method === "POST") {
				// Clone current request because we can't use it after reading headers later
				const curRequest = request.clone();

				log("Intercepting guest post request");

				// Grab the path so we can check in PHP if it's a CP_DIRECTORY URL
				let url = new URL(curRequest.url);
				let path = url.pathname;

				// First get the current csrf key from the server
				fetch(`${BASE_URL}index.php?app=core&module=system&controller=ajax&do=getCsrfKey&path=${path}`)
					.then((response) => response.json())
					.then((response) => {
						log(`Got new csrf key: ${response.key}`);

						// Create new header object starting with existing headers and adding csrf
						const headers = new Headers(curRequest.headers);
						headers.set("X-Csrf-Token", response.key);

						const newRequest = new Request(curRequest, {
							headers,
							credentials: curRequest.credentials,
							referrer: curRequest.referrer,
						});

						// Send cloned request
						resolve(fetch(newRequest));
					})
					.catch((err) => {
						console.log(err);
						reject(err);
					});

				return;
			}

			resolve(fetch(request));
		}).catch((err) => {
			console.log(err);
		})
	);
});

// ------------------------------------------------
// Helpers
// ------------------------------------------------
const log = (message) => {
	if (DEBUG) {
		if (typeof message === "string") {
			message = `SW: ${message}`;
		}

		console.log(message);
	}
};

const cacheAssets = () => {
	return caches.open(CACHE_NAME).then((cache) => {
		return cache.addAll(CACHED_ASSETS);
	});
};

const pluralize = (word, params) => {
	let i = 0;

	if (!Array.isArray(params)) {
		params = [params];
	}

	word = word.replace(/\{(!|\d+?)?#(.*?)\}/g, (a, b, c, d) => {
		// {# [1:count][?:counts]}
		if (!b || b == "!") {
			b = i;
			i++;
		}

		let value;
		let fallback;
		let output = "";
		let replacement = params[b] + "";

		c.replace(/\[(.+?):(.+?)\]/g, (w, x, y, z) => {
			if (x == "?") {
				fallback = y.replace("#", replacement);
			} else if (x.charAt(0) == "%" && x.substring(1) == replacement.substring(0, x.substring(1).length)) {
				value = y.replace("#", replacement);
			} else if (x.charAt(0) == "*" && x.substring(1) == replacement.substr(-x.substring(1).length)) {
				value = y.replace("#", replacement);
			} else if (x == replacement) {
				value = y.replace("#", replacement);
			}
		});

		output = a.replace(/^\{/, "").replace(/\}$/, "").replace("!#", "");
		output = output.replace(b + "#", replacement).replace("#", replacement);
		output = output.replace(/\[.+\]/, value == null ? fallback : value).trim();

		return output;
	});

	return word;
};