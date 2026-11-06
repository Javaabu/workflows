// No server here: answer the builder's save with the problems the server would report
const realFetch = window.fetch.bind(window)
window.fetch = async (input: RequestInfo | URL, init?: RequestInit) => {
  if (String(input).endsWith("/example/save")) {
    console.info("PUT", input, JSON.parse(String(init?.body ?? "{}")))
    return new Response(JSON.stringify({ message: "Draft saved (example).", errors: {}, unreachable: [] }), {
      status: 200,
    })
  }
  return realFetch(input, init)
}

await import("../resources/js/blade/builder")

document.getElementById("direction")!.addEventListener("click", () => {
  const rtl = document.documentElement.dir !== "rtl"
  document.documentElement.dir = rtl ? "rtl" : "ltr"
  document.documentElement.lang = rtl ? "dv" : "en"
})

export {}
