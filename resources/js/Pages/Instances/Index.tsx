import { Alert, Button } from "@javaabu/forms"
import { InstancesTable } from "../../components/InstancesTable"
import { PageHeader } from "../../components/PageHeader"
import { PaginationNav } from "../../components/PaginationNav"
import type { InstancesIndexProps } from "../../types"

/** workflows::Instances/Index — a workflow's instances, by status */
export default function Index({
  record,
  instances,
  pagination,
  filter,
  statuses,
  urls,
  strings,
}: InstancesIndexProps): React.JSX.Element {
  return (
    <div className="flex flex-col gap-6" data-workflows-instances="">
      <PageHeader
        title={strings.instances ?? "Instances"}
        description={record.name}
        backUrl={urls.back}
        backLabel={strings.back}
      />

      <form method="GET" action={urls.index} className="flex flex-wrap items-end gap-2">
        <div className="flex flex-col gap-1">
          <label htmlFor="workflows-status" className="text-sm font-medium">
            {strings.status}
          </label>
          <select
            id="workflows-status"
            name="status"
            defaultValue={filter ?? ""}
            className="bg-background h-9 rounded-md border px-2"
          >
            <option value="">{strings.all_statuses}</option>
            {statuses.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
        <Button type="submit" color="outline-secondary">
          {strings.filter}
        </Button>
      </form>

      {instances.length === 0 ? (
        <Alert type="info">{strings.no_instances}</Alert>
      ) : (
        <>
          <InstancesTable items={instances} strings={strings} showWorkflow={false} />
          <PaginationNav pagination={pagination} strings={strings} />
        </>
      )}
    </div>
  )
}
