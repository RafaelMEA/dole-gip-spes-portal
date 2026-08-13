import { useState } from "react"
import type { FormEvent } from "react"
import { Link, useNavigate } from "react-router-dom"
import { Eye, EyeOff, Loader2 } from "lucide-react"
import { useAuth } from "@/auth/useAuth"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { ApiError } from "@/lib/api"
import { useToast } from "@/toast/useToast"

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

interface RegisterValues {
  name: string
  email: string
  password: string
  passwordConfirmation: string
}

function validate(values: RegisterValues): Record<string, string> {
  const errors: Record<string, string> = {}

  if (!values.name.trim()) {
    errors.name = "Please enter your full name."
  }

  if (!values.email.trim()) {
    errors.email = "Please enter your email address."
  } else if (!EMAIL_PATTERN.test(values.email)) {
    errors.email = "Please enter a valid email address."
  }

  if (!values.password) {
    errors.password = "Please choose a password."
  } else {
    if (values.password.length < 8) {
      errors.password = "Password must be at least 8 characters."
    } else if (
      !/[a-z]/.test(values.password) ||
      !/[A-Z]/.test(values.password) ||
      !/\d/.test(values.password)
    ) {
      errors.password = "Password must include an uppercase letter, a lowercase letter, and a number."
    }
  }

  if (values.password !== values.passwordConfirmation) {
    errors.passwordConfirmation = "Passwords do not match."
  }

  return errors
}

export function RegisterPage() {
  const { register } = useAuth()
  const { toast } = useToast()
  const navigate = useNavigate()

  const [name, setName] = useState("")
  const [email, setEmail] = useState("")
  const [password, setPassword] = useState("")
  const [passwordConfirmation, setPasswordConfirmation] = useState("")
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmation, setShowConfirmation] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)

  function mergeFieldErrors(errors: Record<string, string[]>) {
    const merged: Record<string, string> = {}
    for (const [key, messages] of Object.entries(errors)) {
      merged[key] = messages[0]
    }
    return merged
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setError(null)

    const localErrors = validate({ name, email, password, passwordConfirmation })
    setFieldErrors(localErrors)
    if (Object.keys(localErrors).length > 0) {
      return
    }

    setSubmitting(true)
    try {
      await register(name, email, password, passwordConfirmation)
      toast({
        title: "Account created",
        description: "Your student account has been created. Please sign in to continue.",
        variant: "success",
      })
      navigate("/login", { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(mergeFieldErrors(err.errors ?? {}))
        if (err.status !== 422) {
          setError(err.message)
        }
        toast({ title: "Registration failed", description: err.message, variant: "error" })
      } else {
        setError("An unexpected error occurred. Please try again.")
        toast({
          title: "Registration failed",
          description: "An unexpected error occurred. Please try again.",
          variant: "error",
        })
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="space-y-1.5">
        <h2 className="text-xl font-semibold tracking-tight">Create your account</h2>
        <p className="text-sm text-muted-foreground">
          Registration is for student applicants of the DOLE programs.
        </p>
      </div>

      {error ? (
        <p
          role="alert"
          className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive"
        >
          {error}
        </p>
      ) : null}

      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        <div className="space-y-2">
          <Label htmlFor="name">Full name</Label>
          <Input
            id="name"
            name="name"
            type="text"
            autoComplete="name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            aria-invalid={fieldErrors.name ? true : undefined}
            aria-describedby={fieldErrors.name ? "name-error" : undefined}
            placeholder="Juan Dela Cruz"
            disabled={submitting}
          />
          {fieldErrors.name ? (
            <p id="name-error" className="text-sm text-destructive">
              {fieldErrors.name}
            </p>
          ) : null}
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email address</Label>
          <Input
            id="email"
            name="email"
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            aria-invalid={fieldErrors.email ? true : undefined}
            aria-describedby={fieldErrors.email ? "email-error" : undefined}
            placeholder="you@example.com"
            disabled={submitting}
          />
          {fieldErrors.email ? (
            <p id="email-error" className="text-sm text-destructive">
              {fieldErrors.email}
            </p>
          ) : null}
        </div>

        <div className="space-y-2">
          <Label htmlFor="password">Password</Label>
          <div className="relative">
            <Input
              id="password"
              name="password"
              type={showPassword ? "text" : "password"}
              autoComplete="new-password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              aria-invalid={fieldErrors.password ? true : undefined}
              aria-describedby={
                fieldErrors.password ? "password-error" : "password-hint"
              }
              className="pr-10"
              placeholder="At least 8 characters"
              disabled={submitting}
            />
            <button
              type="button"
              onClick={() => setShowPassword((value) => !value)}
              className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label={showPassword ? "Hide password" : "Show password"}
              tabIndex={-1}
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
          <p id="password-hint" className="text-xs text-muted-foreground">
            At least 8 characters with an uppercase letter, a lowercase letter, and a number.
          </p>
          {fieldErrors.password ? (
            <p id="password-error" className="text-sm text-destructive">
              {fieldErrors.password}
            </p>
          ) : null}
        </div>

        <div className="space-y-2">
          <Label htmlFor="passwordConfirmation">Confirm password</Label>
          <div className="relative">
            <Input
              id="passwordConfirmation"
              name="password_confirmation"
              type={showConfirmation ? "text" : "password"}
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              aria-invalid={fieldErrors.passwordConfirmation ? true : undefined}
              aria-describedby={
                fieldErrors.passwordConfirmation ? "password-confirmation-error" : undefined
              }
              className="pr-10"
              placeholder="Re-enter your password"
              disabled={submitting}
            />
            <button
              type="button"
              onClick={() => setShowConfirmation((value) => !value)}
              className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-md text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-3 focus-visible:ring-ring/50"
              aria-label={showConfirmation ? "Hide confirmation" : "Show confirmation"}
              tabIndex={-1}
            >
              {showConfirmation ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
          {fieldErrors.passwordConfirmation ? (
            <p id="password-confirmation-error" className="text-sm text-destructive">
              {fieldErrors.passwordConfirmation}
            </p>
          ) : null}
        </div>

        <Button type="submit" className="w-full" disabled={submitting}>
          {submitting ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
          {submitting ? "Creating account..." : "Create account"}
        </Button>
      </form>

      <p className="text-center text-sm text-muted-foreground">
        Already have an account?{" "}
        <Link to="/login" className="font-medium text-primary underline-offset-4 hover:underline">
          Sign in
        </Link>
      </p>
    </div>
  )
}
