import { readdirSync, readFileSync, statSync } from "node:fs"
import { join } from "node:path"
import { describe, expect, it } from "vitest"

const root = join(__dirname, "../../resources/js")

function files(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? files(path) : [path]
  })
}

describe("styles", () => {
  // Inertia responses don't get manifest's injected tags, so Vite must bundle the CSS with the pages
  it.each(files(join(root, "Pages")))("%s imports the package styles", (page) => {
    expect(readFileSync(page, "utf8")).toContain('import "../../../css/workflows.css"')
  })

  it("comes with @javaabu/workflows", () => {
    expect(readFileSync(join(root, "index.ts"), "utf8")).toContain('import "../css/workflows.css"')
  })

  // Blade pages load workflows::styles; the prebuilt entry must not carry a second copy
  it("stays out of the Blade entry", () => {
    expect(readFileSync(join(root, "blade/builder.ts"), "utf8")).not.toMatch(/\.css["']/)
  })
})
