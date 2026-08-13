import { apiRequest, requestCsrfCookie } from "@/lib/api"
import type { User } from "@/types/auth"

interface UserResponse {
  data: User
}

export async function getCurrentUser(): Promise<User> {
  const response = await apiRequest<UserResponse>("/api/user")
  return response.data
}

export async function login(email: string, password: string): Promise<User> {
  await requestCsrfCookie()
  const response = await apiRequest<UserResponse>("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  })
  return response.data
}

export async function registerStudent(
  name: string,
  email: string,
  password: string,
  passwordConfirmation: string,
): Promise<User> {
  await requestCsrfCookie()
  const response = await apiRequest<UserResponse>("/api/register", {
    method: "POST",
    body: JSON.stringify({
      name,
      email,
      password,
      password_confirmation: passwordConfirmation,
    }),
  })
  return response.data
}

export async function logout(): Promise<void> {
  await requestCsrfCookie()
  await apiRequest<void>("/api/logout", { method: "POST" })
}
