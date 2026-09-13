export interface Attachment {
    id: number;
    original_name: string;
    mime_type: string;
    size: number;
    created_at: string;
    updated_at: string;
    /** Relative path, e.g. "/api/attachments/5/download" — same-origin, session-cookie authenticated. */
    download_url: string;
}

export interface AttachmentsResponse {
    data: Attachment[];
}

// `AttachmentController@store` responds with `AttachmentResource::collection(...)`
// even for a single uploaded file — same shape as the index listing.
export type AttachmentUploadResponse = AttachmentsResponse;
