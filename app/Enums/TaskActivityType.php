<?php

namespace App\Enums;

enum TaskActivityType: string
{
    case TaskCreated = 'task_created';
    case StatusChanged = 'status_changed';
    case DueAtChanged = 'due_at_changed';
    case TagsChanged = 'tags_changed';
    case AttachmentsAdded = 'attachments_added';
    case AttachmentRemoved = 'attachment_removed';
}
