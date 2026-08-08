import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    watch: {
      // Docker Desktop bind mounts on Windows don't reliably forward inotify
      // events, so Vite's default watcher can miss file changes. Polling
      // guarantees HMR/rebuilds actually pick up edits made on the host.
      usePolling: true,
    },
  },
})
