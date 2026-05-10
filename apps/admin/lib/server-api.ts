import { createApiClient } from "@smartnews/api-client";
import type { AuthenticatedUser } from "@smartnews/types";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";

function baseUrl() {
  return process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://api.localhost:8080/api/v1";
}

export async function serverApiClient() {
  const cookieStore = await cookies();

  return createApiClient({
    apiBaseUrl: baseUrl(),
    headers: {
      cookie: cookieStore.toString(),
    },
    credentials: "include",
  });
}

export async function requireAdminUser(): Promise<AuthenticatedUser> {
  const api = await serverApiClient();

  try {
    const response = await api.auth.me();

    if (response.data.role !== "admin") {
      redirect("/login");
    }

    return response.data;
  } catch {
    redirect("/login");
  }
}
