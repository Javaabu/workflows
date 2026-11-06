import type { ReactNode } from "react"

export interface PageHeaderProps {
  title: string
  description?: string | null
  backUrl?: string
  backLabel?: string
  actions?: ReactNode
}

/** A page's heading: an optional back link, the title, a description and actions */
export function PageHeader({ title, description, backUrl, backLabel, actions }: PageHeaderProps): React.JSX.Element {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div className="flex min-w-0 flex-col gap-1">
        {backUrl ? (
          <a href={backUrl} className="text-muted-foreground text-sm hover:underline">
            {backLabel ?? "Back"}
          </a>
        ) : null}
        <h1 className="text-2xl font-semibold" dir="auto">
          {title}
        </h1>
        {description ? (
          <p className="text-muted-foreground" dir="auto">
            {description}
          </p>
        ) : null}
      </div>
      {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
    </div>
  )
}
