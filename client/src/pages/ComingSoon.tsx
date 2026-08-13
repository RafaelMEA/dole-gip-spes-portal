import { Construction } from "lucide-react"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"

interface ComingSoonPageProps {
  title: string
  description: string
}

export function ComingSoonPage({ title, description }: ComingSoonPageProps) {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Construction className="size-4 text-muted-foreground" aria-hidden="true" />
            Under construction
          </CardTitle>
          <CardDescription>
            This section is not yet available. It will be enabled in a future update of the portal.
          </CardDescription>
        </CardHeader>
        <CardContent className="text-sm text-muted-foreground">
          If you need assistance, please contact your nearest DOLE office.
        </CardContent>
      </Card>
    </div>
  )
}
