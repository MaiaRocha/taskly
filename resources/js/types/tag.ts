export interface Tag {
    id: number;
    name: string;
    color: string;
    created_at: string;
    updated_at: string;
}

export interface TagsResponse {
    data: Tag[];
}

export interface TagResponse {
    data: Tag;
}

/** Editable fields only, matching `StoreTagRequest` — `name` max 30, `color` one of the 6 auxiliary colors. */
export interface TagFormPayload {
    name: string;
    color: string;
}
