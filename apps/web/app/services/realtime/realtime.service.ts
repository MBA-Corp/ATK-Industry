import { getEcho } from "~/lib/echo";

export function subscribeToPrivateChannel(channelName: string) {
  const echo = getEcho();

  if (!echo) {
    return null;
  }

  return echo.private(channelName);
}

export function leaveChannel(channelName: string): void {
  const echo = getEcho();

  if (!echo) {
    return;
  }

  echo.leave(channelName);
}
