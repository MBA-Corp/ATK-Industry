import Echo from "laravel-echo";
import Pusher from "pusher-js";

let echoInstance: Echo<"reverb"> | null = null;

function getReverbConfig() {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  const host = import.meta.env.VITE_REVERB_HOST;
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? "http";

  return {
    key,
    host,
    port,
    scheme,
  };
}

export function getEcho(): Echo<"reverb"> | null {
  if (typeof window === "undefined") {
    return null;
  }

  if (echoInstance) {
    return echoInstance;
  }

  const config = getReverbConfig();

  if (!config.key || !config.host) {
    return null;
  }

  window.Pusher = Pusher;

  echoInstance = new Echo({
    broadcaster: "reverb",
    key: config.key,
    wsHost: config.host,
    wsPort: config.port,
    wssPort: config.port,
    forceTLS: config.scheme === "https",
    enabledTransports: ["ws", "wss"],
  });

  return echoInstance;
}

export function disconnectEcho(): void {
  if (!echoInstance) {
    return;
  }

  echoInstance.disconnect();
  echoInstance = null;
}
