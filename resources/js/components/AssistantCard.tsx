import { Card, Form, Submit, Textarea } from "@javaabu/forms"
import type { Strings } from "../types"

/** The AI assistant: a description, sent to the assistant route */
export function AssistantCard({
  url,
  title,
  help,
  strings,
}: {
  url: string
  title: string
  help: string
  strings: Strings
}): React.JSX.Element {
  return (
    <Card title={title}>
      <Form action={url} method="POST">
        <div className="flex flex-col gap-3" data-workflows-assistant="">
          <p className="text-muted-foreground text-sm">{help}</p>
          <Textarea
            name="description"
            label={title}
            rows={4}
            required
            dir="auto"
            placeholder={strings.assistant_placeholder}
          />
          <div>
            <Submit>{strings.assistant_submit}</Submit>
          </div>
        </div>
      </Form>
    </Card>
  )
}
