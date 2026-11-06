import { StencilForm } from "@javaabu/stencil"
import { PageHeader } from "../../components/PageHeader"
import { StatusAlert } from "../../components/StatusAlert"
import type { WorkflowsEditProps } from "../../types"

/** workflows::Workflows/Edit — a new workflow, or a workflow's settings */
export default function Edit({
  form,
  record,
  title,
  back_url,
  status,
  strings,
}: WorkflowsEditProps): React.JSX.Element {
  return (
    <div className="flex flex-col gap-6" data-workflows-settings="">
      <PageHeader title={title} backUrl={back_url} backLabel={strings.back} />
      <StatusAlert status={status} />
      <StencilForm key={record?.key ?? "new"} schema={form} />
    </div>
  )
}
