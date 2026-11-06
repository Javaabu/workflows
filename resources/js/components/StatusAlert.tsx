import { Alert } from "@javaabu/forms"

/** The flash message of the last action */
export function StatusAlert({ status }: { status?: string | null }): React.JSX.Element | null {
  if (!status) return null

  return (
    <div role="status">
      <Alert type="success">{status}</Alert>
    </div>
  )
}
