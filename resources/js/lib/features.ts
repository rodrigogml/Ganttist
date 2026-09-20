declare global {
  interface Window {
    __GANTTIST_FEATURES__?: { documents?: boolean }
  }
}

export const features = {
  documents: window.__GANTTIST_FEATURES__?.documents !== false,
}
