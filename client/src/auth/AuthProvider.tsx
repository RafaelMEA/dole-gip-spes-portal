import { useCallback, useEffect, useMemo, useState } from "react"
import type { ReactNode } from "react"
import { ApiError } from "@/lib/api"
import * as authApi from "@/auth/authApi"
import { AuthContext } from "@/auth/auth-context"
import type { AuthContextValue } from "@/auth/auth-context"
import type { User } from "@/types/auth"

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [isLoading, setIsLoading] = useState(true)

  const refreshUser = useCallback(async () => {
    const currentUser = await authApi.getCurrentUser()
    setUser(currentUser)
  }, [])

  useEffect(() => {
    let active = true

    authApi
      .getCurrentUser()
      .then((currentUser) => {
        if (active) setUser(currentUser)
      })
      .catch((error: unknown) => {
        if (!(error instanceof ApiError && error.status === 401)) {
          console.error("Failed to load the current user:", error)
        }
      })
      .finally(() => {
        if (active) setIsLoading(false)
      })

    return () => {
      active = false
    }
  }, [])

  const login = useCallback<AuthContextValue["login"]>(async (email, password) => {
    const nextUser = await authApi.login(email, password)
    setUser(nextUser)
    return nextUser
  }, [])

  const register = useCallback<AuthContextValue["register"]>(
    async (name, email, password, passwordConfirmation) => {
      await authApi.registerStudent(name, email, password, passwordConfirmation)
    },
    [],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      setUser(null)
    }
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isAuthenticated: user !== null,
      isLoading,
      login,
      register,
      logout,
      refreshUser,
    }),
    [user, isLoading, login, register, logout, refreshUser],
  )

  return <AuthContext value={value}>{children}</AuthContext>
}
