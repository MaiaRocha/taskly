import { defineStore } from 'pinia';
import { ref } from 'vue';
import { http } from '../lib/http';
import type { Tag, TagFormPayload, TagResponse, TagsResponse } from '../types/tag';

export type TagsStatus = 'idle' | 'loading' | 'loaded' | 'error';

export const useTagsStore = defineStore('tags', () => {
    const tags = ref<Tag[]>([]);
    const status = ref<TagsStatus>('idle');

    let inFlight: Promise<void> | null = null;

    // Tags are global per user (not scoped per Project), but still private
    // session data — bumped by reset() so a request started before a logout
    // can't repopulate the store for the next session.
    let sessionEpoch = 0;

    /**
     * Loads the user's tags. Safe to call from multiple components: a
     * request already in flight is shared (never duplicated), and once
     * `loaded`, subsequent calls are no-ops unless `force` is passed.
     */
    function fetchTags(force = false): Promise<void> {
        if (status.value === 'loading' && inFlight) {
            return inFlight;
        }

        if (status.value === 'loaded' && !force) {
            return Promise.resolve();
        }

        status.value = 'loading';

        const capturedEpoch = sessionEpoch;

        const request = (async () => {
            try {
                const { data } = await http.get<TagsResponse>('/api/tags');

                if (capturedEpoch !== sessionEpoch) {
                    return;
                }

                tags.value = data.data;
                status.value = 'loaded';
            } catch {
                if (capturedEpoch !== sessionEpoch) {
                    return;
                }

                status.value = 'error';
            } finally {
                if (capturedEpoch === sessionEpoch) {
                    inFlight = null;
                }
            }
        })();

        inFlight = request;

        return request;
    }

    /**
     * Creates a tag and appends the server's response to the list. If a
     * reset() happens while this request is in flight (logout, session
     * end), the response is discarded instead of repopulating a store that
     * no longer belongs to the current session.
     */
    async function createTag(payload: TagFormPayload): Promise<Tag> {
        const capturedEpoch = sessionEpoch;

        const { data } = await http.post<TagResponse>('/api/tags', payload);

        if (capturedEpoch === sessionEpoch) {
            tags.value = [...tags.value, data.data];
        }

        return data.data;
    }

    /**
     * Clears the store back to its initial state and invalidates any
     * request still in flight, so a stale response from a previous
     * authenticated session can never repopulate it after this point.
     */
    function reset(): void {
        sessionEpoch++;
        tags.value = [];
        status.value = 'idle';
        inFlight = null;
    }

    return {
        tags,
        status,
        fetchTags,
        createTag,
        reset,
    };
});
