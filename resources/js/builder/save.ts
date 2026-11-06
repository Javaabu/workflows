import type { Definition, Problems } from "../types"

export interface SaveResult {
  ok: boolean
  message?: string
  errors: Problems
  unreachable: string[]
  definition?: Definition
}

function csrfToken(explicit?: string): string | undefined {
  if (explicit) return explicit
  if (typeof document === "undefined") return undefined
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
  if (meta) return meta
  const cookie = document.cookie.split("; ").find((part) => part.startsWith("XSRF-TOKEN="))
  return cookie ? decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)) : undefined
}

/** Save the draft's definition: PUT it as JSON and read back its problems */
export async function saveDefinition(url: string, definition: Definition, csrf?: string): Promise<SaveResult> {
  const token = csrfToken(csrf)
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
  }
  if (token)
    headers[csrf || document.querySelector('meta[name="csrf-token"]') ? "X-CSRF-TOKEN" : "X-XSRF-TOKEN"] = token

  const response = await fetch(url, {
    method: "PUT",
    headers,
    credentials: "same-origin",
    body: JSON.stringify({ definition }),
  })

  const body = (await response.json().catch(() => ({}))) as {
    message?: string
    errors?: Problems
    unreachable?: string[]
    definition?: Definition
  }

  return {
    ok: response.ok,
    message: body.message,
    errors: body.errors ?? {},
    unreachable: body.unreachable ?? [],
    definition: body.definition,
  }
}
