import { Alert, Button, Form, LinkButton } from "@javaabu/forms"
import { AssistantCard } from "../../components/AssistantCard"
import { WorkflowStatus } from "../../components/Badges"
import { PageHeader } from "../../components/PageHeader"
import { StatusAlert } from "../../components/StatusAlert"
import { WorkflowBuilder } from "../../builder/WorkflowBuilder"
import type { WorkflowsShowProps } from "../../types"

/** workflows::Workflows/Show — the builder */
export default function Show({ workflow, builder, can, urls, status, strings }: WorkflowsShowProps): React.JSX.Element {
  const confirm = (question: string | undefined) => (event: React.FormEvent) => {
    if (question && !window.confirm(question)) event.preventDefault()
  }

  return (
    <div className="flex flex-col gap-6" data-workflows-builder-page="">
      <PageHeader
        title={workflow.name}
        description={workflow.description}
        backUrl={urls.index}
        backLabel={strings.title}
        actions={
          <>
            <WorkflowStatus workflow={workflow} strings={strings} />
            {can.update && urls.edit ? (
              <LinkButton url={urls.edit} color="outline-secondary" startIcon="settings">
                {strings.settings}
              </LinkButton>
            ) : null}
            {urls.test ? (
              <LinkButton url={urls.test} color="outline-secondary" startIcon="play">
                {strings.test}
              </LinkButton>
            ) : null}
            {urls.versions ? (
              <LinkButton url={urls.versions} color="outline-secondary">
                {strings.versions}
              </LinkButton>
            ) : null}
            {can.view_instances && urls.instances ? (
              <LinkButton url={urls.instances} color="outline-secondary">
                {strings.instances}
              </LinkButton>
            ) : null}
            {urls.export ? (
              <a href={urls.export} className="text-sm hover:underline">
                {strings.export}
              </a>
            ) : null}
            {can.publish && workflow.has_draft && urls.publish ? (
              <Form action={urls.publish} method="POST" onSubmitCapture={confirm(strings.confirm_publish)}>
                <Button type="submit" color="success" startIcon="check">
                  {strings.publish}
                </Button>
              </Form>
            ) : null}
            {can.update && urls.discard ? (
              <Form action={urls.discard} method="DELETE" onSubmitCapture={confirm(strings.confirm_discard)}>
                <Button type="submit" color="outline-danger">
                  {strings.discard}
                </Button>
              </Form>
            ) : null}
          </>
        }
      />

      <StatusAlert status={status} />

      {workflow.is_locked ? (
        <Alert type="info">
          <strong>{strings.read_only}</strong> {strings.locked_help}
        </Alert>
      ) : null}

      {urls.assistant ? (
        <AssistantCard
          url={urls.assistant}
          title={strings.assistant_change ?? ""}
          help={strings.assistant_change_help ?? ""}
          strings={strings}
        />
      ) : null}

      <WorkflowBuilder builder={builder} strings={strings} saveUrl={can.update ? urls.save : undefined} />
    </div>
  )
}
