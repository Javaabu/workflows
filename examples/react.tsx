import { StrictMode, useState } from "react"
import { createRoot } from "react-dom/client"
import { FormsProvider } from "@javaabu/forms"
import { html } from "@javaabu/forms/html"
import {
  InstancesShow,
  WorkflowBuilder,
  TasksIndex,
  WorkflowsTest,
  type InstancesShowProps,
  type TasksIndexProps,
  type WorkflowsShowProps,
  type WorkflowsTestProps,
} from "@javaabu/workflows"
import instancesShow from "../tests/js/fixtures/instances-show.json"
import tasksIndex from "../tests/js/fixtures/tasks-index.json"
import workflowsShow from "../tests/js/fixtures/workflows-show.json"
import workflowsTest from "../tests/js/fixtures/workflows-test.json"
import "../resources/css/workflows.css"

const pages = {
  builder: "The builder (workflows::Workflows/Show)",
  tester: "The tester (workflows::Workflows/Test)",
  instance: "A task (workflows::Instances/Show)",
  tasks: "My tasks (workflows::Tasks/Index)",
} as const

const save = async () => ({ ok: true, message: "Draft saved (example).", errors: {}, unreachable: [] })

function App() {
  const [rtl, setRtl] = useState(false)
  const [page, setPage] = useState<keyof typeof pages>("builder")
  const builder = workflowsShow as unknown as WorkflowsShowProps

  return (
    <main dir={rtl ? "rtl" : "ltr"} lang={rtl ? "dv" : "en"}>
      <header>
        <h1>React pages</h1>
        <p>
          The Inertia pages with fixtures from the PHP tests. <a href="./index.html">Blade builder</a> ·{" "}
          <button type="button" onClick={() => setRtl(!rtl)}>
            RTL / ދިވެހި
          </button>
        </p>
        <nav>
          {Object.entries(pages).map(([key, label]) => (
            <button
              key={key}
              type="button"
              aria-pressed={page === key}
              onClick={() => setPage(key as keyof typeof pages)}
            >
              {label}
            </button>
          ))}
        </nav>
      </header>
      <FormsProvider ui={html}>
        {page === "builder" ? (
          <WorkflowBuilder builder={builder.builder} strings={builder.strings} onSave={save} />
        ) : null}
        {page === "tester" ? <WorkflowsTest {...(workflowsTest as unknown as WorkflowsTestProps)} /> : null}
        {page === "instance" ? <InstancesShow {...(instancesShow as unknown as InstancesShowProps)} /> : null}
        {page === "tasks" ? <TasksIndex {...(tasksIndex as unknown as TasksIndexProps)} /> : null}
      </FormsProvider>
    </main>
  )
}

createRoot(document.getElementById("app")!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
