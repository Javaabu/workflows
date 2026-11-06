import { Badge } from "../../components/Badges"
import { PageHeader } from "../../components/PageHeader"
import type { WorkflowsVersionsProps } from "../../types"

/** workflows::Workflows/Versions */
export default function Versions({ record, versions, back_url, strings }: WorkflowsVersionsProps): React.JSX.Element {
  return (
    <div className="flex flex-col gap-6" data-workflows-versions="">
      <PageHeader
        title={strings.versions ?? "Versions"}
        description={record.name}
        backUrl={back_url}
        backLabel={strings.back}
      />
      <div className="overflow-x-auto rounded-md border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50">
            <tr>
              <th scope="col" className="px-3 py-2 text-start font-medium">
                {strings.version}
              </th>
              <th scope="col" className="px-3 py-2 text-start font-medium">
                {strings.published_at}
              </th>
              <th scope="col" className="px-3 py-2 text-start font-medium">
                {strings.author}
              </th>
              <th scope="col" className="px-3 py-2 text-start font-medium">
                {strings.instances}
              </th>
            </tr>
          </thead>
          <tbody>
            {versions.map((version) => (
              <tr key={version.id} className="border-t" data-version={version.number}>
                <td className="px-3 py-2">
                  <span className="inline-flex items-center gap-2">
                    {version.number}
                    {version.is_draft ? (
                      <Badge label={strings.draft ?? "Draft"} color="warning" />
                    ) : version.is_current ? (
                      <Badge label={strings.current ?? "Current"} color="success" />
                    ) : null}
                  </span>
                </td>
                <td className="px-3 py-2">{version.published_at_label ?? "—"}</td>
                <td className="px-3 py-2" dir="auto">
                  {version.author ?? "—"}
                </td>
                <td className="px-3 py-2">{version.instances_count}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
