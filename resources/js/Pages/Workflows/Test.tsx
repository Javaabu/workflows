// Vite bundles the package styles with every page that uses them
import "../../../css/workflows.css"
import { Alert, Button, Checkbox, Form, Textarea } from "@javaabu/forms"
import * as React from "react"
import { PageHeader } from "../../components/PageHeader"
import { WorkflowGraph } from "../../builder/WorkflowGraph"
import type { SimulationData, Strings, WorkflowsTestProps } from "../../types"

/** The tester's result: the status, and the path step by step */
export function SimulationResult({
  simulation,
  strings,
}: {
  simulation: SimulationData
  strings: Strings
}): React.JSX.Element {
  const type = simulation.status === "completed" ? "success" : simulation.status === "waiting" ? "info" : "warning"

  return (
    <section
      className="flex flex-col gap-3"
      aria-labelledby="workflows-result"
      data-simulation-status={simulation.status}
    >
      <h2 id="workflows-result" className="text-lg font-semibold">
        {strings.result}
      </h2>
      <div role="status">
        <Alert type={type}>
          <strong>{strings[`status_${simulation.status}`]}</strong>
          {simulation.message ? ` — ${simulation.message}` : null}
        </Alert>
      </div>
      <ol className="flex flex-col gap-2" aria-label={strings.path}>
        {simulation.path.map((step, index) => (
          <li key={`${step.step}-${index}`} className="rounded-md border p-3" data-path-step={step.step}>
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-muted-foreground">{index + 1}.</span>
              <span className="font-medium" dir="auto">
                {step.name}
              </span>
            </div>
            {[...step.actions, ...(step.transition_actions ?? [])].map((action, i) => (
              <p key={i} className="ms-6 text-sm">
                <span className="text-muted-foreground">{strings.would_run}:</span>{" "}
                <span dir="auto">{action.description}</span>
              </p>
            ))}
            {step.transition ? (
              <p className="ms-6 text-sm">
                <span className="text-muted-foreground">{strings.took}:</span>{" "}
                <span dir="auto">{step.transition_label ?? step.transition}</span>{" "}
                <span className="text-muted-foreground">({strings[`reason_${step.reason}`]})</span>
              </p>
            ) : null}
            {step.options ? (
              <ul className="ms-6 list-disc text-sm">
                {step.options.map((option) => (
                  <li
                    key={option.key}
                    className={option.available ? undefined : "text-muted-foreground line-through"}
                    dir="auto"
                  >
                    {option.label ?? option.key}
                  </li>
                ))}
              </ul>
            ) : null}
          </li>
        ))}
      </ol>
      {Object.keys(simulation.data).length > 0 ? (
        <details>
          <summary className="cursor-pointer text-sm font-medium">{strings.final_data}</summary>
          <pre className="bg-muted mt-2 overflow-x-auto rounded-md p-3 text-xs" dir="ltr">
            {JSON.stringify(simulation.data, null, 2)}
          </pre>
        </details>
      ) : null}
    </section>
  )
}

/** workflows::Workflows/Test — the tester: runs without reloading, over JSON */
export default function Test({
  workflow,
  definition,
  catalog,
  tasks,
  input,
  simulation: initial,
  has_draft,
  urls,
  strings,
}: WorkflowsTestProps): React.JSX.Element {
  const [simulation, setSimulation] = React.useState(initial)
  const [error, setError] = React.useState<string | null>(null)

  const run = async (values: Record<string, unknown>) => {
    setError(null)
    const token =
      typeof document !== "undefined"
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
        : undefined
    const response = await fetch(urls.run, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(token ? { "X-CSRF-TOKEN": token } : {}),
      },
      body: JSON.stringify(values),
    })
    const body = (await response.json().catch(() => ({}))) as { simulation?: SimulationData; message?: string }
    if (!response.ok || !body.simulation) {
      setError(body.message ?? strings.invalid_json ?? "")
      return
    }
    setSimulation(body.simulation)
  }

  return (
    <div className="flex flex-col gap-6" data-workflows-tester="">
      <PageHeader
        title={strings.tester ?? "Test"}
        description={strings.tester_help}
        backUrl={urls.back}
        backLabel={workflow.name}
      />

      <Form
        action={urls.run}
        method="POST"
        onSubmit={async (values, event) => {
          // The choices are plain selects: read them from the form itself
          const form = new FormData(event.currentTarget)
          const choices = Object.fromEntries(
            tasks
              .map((task) => [task.key, String(form.get(`choices[${task.key}]`) ?? "")])
              .filter(([, value]) => value !== ""),
          )
          await run({ data: values.data, subject: values.subject, draft: values.draft ? 1 : 0, choices })
        }}
      >
        <div className="flex flex-col gap-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Textarea
              name="data"
              label={strings.sample_data}
              help={strings.sample_data_help}
              rows={5}
              dir="ltr"
              default={input.data}
            />
            <Textarea
              name="subject"
              label={strings.sample_subject}
              help={strings.sample_subject_help}
              rows={5}
              dir="ltr"
              default={input.subject}
            />
          </div>
          {tasks.length > 0 ? (
            <fieldset className="flex flex-col gap-3 rounded-md border p-3">
              <legend className="px-1 font-medium">{strings.choices}</legend>
              <p className="text-muted-foreground text-sm">{strings.choices_help}</p>
              <div className="grid gap-3 md:grid-cols-2">
                {tasks.map((task) => (
                  <div key={task.key} className="flex flex-col gap-1">
                    <label htmlFor={`choice-${task.key}`} className="text-sm font-medium" dir="auto">
                      {task.name}
                    </label>
                    <select
                      id={`choice-${task.key}`}
                      name={`choices[${task.key}]`}
                      defaultValue={input.choices[task.key] ?? ""}
                      className="bg-background h-9 rounded-md border px-2"
                    >
                      <option value="">{strings.stop_here}</option>
                      {task.transitions.map((transition) => (
                        <option key={transition.key} value={transition.key}>
                          {transition.label}
                        </option>
                      ))}
                    </select>
                  </div>
                ))}
              </div>
            </fieldset>
          ) : null}
          {has_draft ? <Checkbox name="draft" label={strings.test_draft} default={input.draft} /> : null}
          <div>
            <Button type="submit" startIcon="play">
              {strings.run}
            </Button>
          </div>
        </div>
      </Form>

      {error ? <Alert type="danger">{error}</Alert> : null}
      {simulation ? <SimulationResult simulation={simulation} strings={strings} /> : null}
      {definition ? (
        <WorkflowGraph
          definition={definition}
          catalog={catalog}
          strings={strings}
          highlight={simulation?.path.map((step) => step.step) ?? []}
        />
      ) : null}
    </div>
  )
}
