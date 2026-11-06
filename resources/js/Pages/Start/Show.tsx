// Vite bundles the package styles with every page that uses them
import "../../../css/workflows.css"
import { Button, Form, Textarea } from "@javaabu/forms"
import { StencilFields } from "@javaabu/stencil"
import { PageHeader } from "../../components/PageHeader"
import { StatusAlert } from "../../components/StatusAlert"
import { formBuilderComponents } from "../../stencil"
import type { StartShowProps } from "../../types"

/** workflows::Start/Show — start a workflow: its start form and a comment */
export default function Show({ workflow, subject, form, urls, status, strings }: StartShowProps): React.JSX.Element {
  return (
    <div className="flex flex-col gap-6" data-workflows-start={workflow.key}>
      <PageHeader
        title={strings.title ?? workflow.name}
        description={subject?.label ?? workflow.description}
        backUrl={urls.back}
        backLabel={strings.back}
      />
      <StatusAlert status={status} />
      <Form action={urls.store} method="POST" files>
        <div className="flex flex-col gap-4">
          {form ? <StencilFields schema={form} components={formBuilderComponents} /> : null}
          <Textarea name="comment" label={strings.comment} rows={3} dir="auto" />
          <div>
            <Button type="submit" startIcon="play">
              {strings.start}
            </Button>
          </div>
        </div>
      </Form>
    </div>
  )
}
