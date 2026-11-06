import { LinkButton } from "@javaabu/forms"
import type { Pagination, Strings } from "../types"

function pageUrl(page: number): string {
  if (typeof window === "undefined") return `?page=${page}`
  const url = new URL(window.location.href)
  url.searchParams.set("page", String(page))
  return url.toString()
}

/** Previous and next links for a paginated list */
export function PaginationNav({
  pagination,
  strings,
}: {
  pagination: Pagination
  strings: Strings
}): React.JSX.Element | null {
  if (pagination.last_page <= 1) return null

  const label = (strings.page_of ?? "Page :page of :pages")
    .replace(":page", String(pagination.current_page))
    .replace(":pages", String(pagination.last_page))

  return (
    <nav className="flex items-center justify-between gap-2" aria-label={label}>
      {pagination.current_page > 1 ? (
        <LinkButton url={pageUrl(pagination.current_page - 1)} color="outline-secondary" size="sm">
          {strings.previous_page}
        </LinkButton>
      ) : (
        <span />
      )}
      <span className="text-muted-foreground text-sm">{label}</span>
      {pagination.current_page < pagination.last_page ? (
        <LinkButton url={pageUrl(pagination.current_page + 1)} color="outline-secondary" size="sm">
          {strings.next_page}
        </LinkButton>
      ) : (
        <span />
      )}
    </nav>
  )
}
