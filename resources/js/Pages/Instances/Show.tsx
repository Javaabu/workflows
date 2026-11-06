// Vite bundles the package styles with every page that uses them
import "../../../css/workflows.css"
import { Button, Card, Errors, Form, Textarea } from "@javaabu/forms"
import { StencilFields } from "@javaabu/stencil"
import { InstanceStatus } from "../../components/Badges"
import { PageHeader } from "../../components/PageHeader"
import { StatusAlert } from "../../components/StatusAlert"
import { formBuilderComponents } from "../../stencil"
import type { HistoryEntry, InstancesShowProps, Strings } from "../../types"

/** An instance's history, oldest first */
export function History({ history, strings }: { history: HistoryEntry[]; strings: Strings }): React.JSX.Element {
  return (
    <section aria-labelledby="workflows-history" className="flex flex-col gap-3">
      <h2 id="workflows-history" className="text-lg font-semibold">
        {strings.history}
      </h2>
      <ol className="flex flex-col gap-2 border-s ps-4">
        {history
          .filter((entry) => entry.event !== "entered")
          .map((entry) => (
            <li key={entry.id} className="flex flex-col" data-history-event={entry.event}>
              <span className="text-sm">
                <span className="font-medium" dir="auto">
                  {entry.label}
                </span>{" "}
                · <span dir="auto">{entry.actor ?? strings.system}</span>
              </span>
              <span className="text-muted-foreground text-xs">{entry.at_label}</span>
              {entry.comment ? (
                <blockquote className="mt-1 border-s-2 ps-2 text-sm" dir="auto">
                  {entry.comment}
                </blockquote>
              ) : null}
              {entry.error ? (
                <span className="text-destructive text-xs" dir="ltr">
                  {entry.error}
                </span>
              ) : null}
            </li>
          ))}
      </ol>
    </section>
  )
}

/** workflows::Instances/Show — an instance: where it is, what it collected, its history, and the current task */
export default function Show({
  instance,
  workflow,
  history,
  entries,
  data,
  transitions,
  form,
  can,
  urls,
  status,
  strings,
}: InstancesShowProps): React.JSX.Element {
  const needsComment = transitions.some((transition) => transition.comment !== "none")

  return (
    <div className="flex flex-col gap-6" data-workflows-instance={instance.id}>
      <PageHeader
        title={instance.subject?.label ?? instance.title}
        description={workflow.name}
        backUrl={urls.back}
        backLabel={strings.back}
        actions={<InstanceStatus instance={instance} />}
      />
      <StatusAlert status={status} />

      <dl className="grid gap-3 rounded-md border p-4 text-sm md:grid-cols-4">
        <div>
          <dt className="text-muted-foreground">{strings.step}</dt>
          <dd className="font-medium" dir="auto">
            {instance.step_name ?? "—"}
          </dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{strings.started_by}</dt>
          <dd dir="auto">{instance.starter ?? "—"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{strings.started}</dt>
          <dd>{instance.started_at_label ?? "—"}</dd>
        </div>
        {instance.status === "active" ? (
          <div>
            <dt className="text-muted-foreground">{strings.assignees}</dt>
            <dd dir="auto">{instance.assignees.length === 0 ? "—" : instance.assignees.join(", ")}</dd>
          </div>
        ) : (
          <div>
            <dt className="text-muted-foreground">{strings.completed_at}</dt>
            <dd>{instance.completed_at_label ?? "—"}</dd>
          </div>
        )}
        {instance.due_at_label ? (
          <div>
            <dt className="text-muted-foreground">{strings.due}</dt>
            <dd className={instance.overdue ? "text-destructive font-medium" : undefined}>
              {instance.due_at_label}
              {instance.overdue ? ` (${strings.overdue})` : null}
            </dd>
          </div>
        ) : null}
      </dl>

      {can.act ? (
        <Card title={strings.your_turn}>
          <Form action={urls.transition} method="POST" files>
            <div className="flex flex-col gap-4" data-workflows-act="">
              {form ? <StencilFields schema={form} components={formBuilderComponents} /> : null}
              {needsComment ? <Textarea name="comment" label={strings.comment} rows={3} dir="auto" /> : null}
              <Errors name="transition" />
              <div className="flex flex-wrap gap-2">
                {transitions.map((transition) => (
                  <Button
                    key={transition.key}
                    type="submit"
                    name="transition"
                    value={transition.key}
                    color={transition.color as "primary"}
                    formNoValidate={transition.skips_form}
                    data-comment={transition.comment}
                    dir="auto"
                  >
                    {transition.label}
                  </Button>
                ))}
              </div>
            </div>
          </Form>
        </Card>
      ) : null}

      {entries.map((entry) => (
        <Card key={entry.step} title={entry.name}>
          <div data-workflows-entry={entry.step}>
            <StencilFields schema={entry.display} components={formBuilderComponents} />
          </div>
        </Card>
      ))}

      {data.length > 0 ? (
        <Card title={strings.data}>
          <dl className="grid gap-2 text-sm md:grid-cols-2">
            {data.map((item) => (
              <div key={item.key}>
                <dt className="text-muted-foreground">{item.key}</dt>
                <dd dir="auto">{item.value}</dd>
              </div>
            ))}
          </dl>
        </Card>
      ) : null}

      <History history={history} strings={strings} />

      {can.cancel ? (
        <details className="rounded-md border p-3">
          <summary className="cursor-pointer text-sm font-medium">{strings.cancel_instance}</summary>
          <Form
            action={urls.cancel}
            method="POST"
            className="mt-3 flex flex-col gap-3"
            onSubmitCapture={(event) => {
              if (!window.confirm(strings.confirm_cancel ?? "")) event.preventDefault()
            }}
          >
            <Textarea name="reason" label={strings.cancel_reason} rows={2} dir="auto" />
            <div>
              <Button type="submit" color="danger">
                {strings.cancel_instance}
              </Button>
            </div>
          </Form>
        </details>
      ) : null}
    </div>
  )
}
