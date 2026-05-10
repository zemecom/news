import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  transpilePackages: ["@smartnews/api-client", "@smartnews/config", "@smartnews/types", "@smartnews/ui"],
};

export default nextConfig;
