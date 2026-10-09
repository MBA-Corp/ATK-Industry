import { reactRouter } from "@react-router/dev/vite";
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vite";

export default defineConfig({
  plugins: [tailwindcss(), reactRouter()],
  server: {
    proxy: {
      "/api": "http://127.0.0.1:8000",
      "/sanctum": "http://127.0.0.1:8000",
      "/login": {
        target: "http://127.0.0.1:8000",
        bypass(req) {
          if (req.method === "GET") return req.url;
        },
      },
      "/logout": "http://127.0.0.1:8000",
    },
  },
  resolve: {
    tsconfigPaths: true,
  },
});
