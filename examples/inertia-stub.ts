/** Stands in for @inertiajs/react in the examples: logs what the pages would send */
function log(method: string, url: string, data?: unknown) {
  console.info(`router.${method}`, url, data)
  document.dispatchEvent(new CustomEvent("example:visit", { detail: { method, url, data } }))
}

export const router = {
  get: (url: string) => log("get", url),
  post: (url: string, data?: unknown) => log("post", url, data),
  put: (url: string, data?: unknown) => log("put", url, data),
  delete: (url: string) => log("delete", url),
}
