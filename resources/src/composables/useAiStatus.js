import { ref } from 'vue';
import http from '../lib/http';
import { useAuthStore } from '../stores/auth';

/**
 * Whether the AI assistant is set up and this user may use it
 * (GET ai/status, fetched once per page load and shared).
 */
const status = ref(null);
let pending = null;

export function useAiStatus() {
  const auth = useAuthStore();

  async function load() {
    if (status.value) return status.value;
    if (!auth.can('ai_assistant')) {
      status.value = { ready: false };
      return status.value;
    }
    pending = pending || http.get('ai/status')
      .then(data => { status.value = data || { ready: false }; })
      .catch(() => { status.value = { ready: false }; })
      .finally(() => { pending = null; });
    await pending;
    return status.value;
  }

  return { status, load };
}
