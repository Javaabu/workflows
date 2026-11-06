import type { Catalog, Definition, Strings } from "../types"
import { Canvas } from "./Canvas"
import { layout } from "./definition"

export interface WorkflowGraphProps {
  definition: Definition
  catalog: Pick<Catalog, "step_types" | "locales">
  strings: Strings
  /** Steps in the order a run visited them, e.g. the tester's path */
  highlight?: string[]
}

/** The diagram, read-only: for the tester and instance pages */
export function WorkflowGraph({ definition, catalog, strings, highlight = [] }: WorkflowGraphProps): React.JSX.Element {
  return (
    <div className="workflows-graph" data-workflow-graph-root="">
      <Canvas
        definition={layout(definition)}
        catalog={catalog}
        strings={strings}
        locale={catalog.locales[0]?.code}
        highlight={highlight}
        readOnly
      />
    </div>
  )
}
