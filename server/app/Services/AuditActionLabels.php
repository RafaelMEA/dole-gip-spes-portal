<?php

namespace App\Services;

/**
 * Human-readable labels for audit actions and application workflow actions.
 *
 * Kept in one place so every history surface (staff timelines, student
 * timelines, contextual entity histories) renders consistent wording.
 */
class AuditActionLabels
{
    /**
     * Labels for audit_logs.action values.
     *
     * @var array<string, string>
     */
    private const AUDIT_ACTIONS = [
        'document.uploaded' => 'Document Uploaded',
        'document.replaced' => 'Document Replaced',
        'document.verified' => 'Document Verified',
        'document.rejected' => 'Document Rejected',
        'deployment_slot.created' => 'Deployment Slot Created',
        'deployment_slot.updated' => 'Deployment Slot Updated',
        'deployment_slot.activated' => 'Deployment Slot Activated',
        'deployment_slot.deactivated' => 'Deployment Slot Deactivated',
        'deployment_site.created' => 'Deployment Site Created',
        'deployment_site.updated' => 'Deployment Site Updated',
        'deployment_site.activated' => 'Deployment Site Activated',
        'deployment_site.deactivated' => 'Deployment Site Deactivated',
        'host_agency.created' => 'Host Agency Created',
        'host_agency.updated' => 'Host Agency Updated',
        'host_agency.activated' => 'Host Agency Activated',
        'host_agency.deactivated' => 'Host Agency Deactivated',
        'assignment.created' => 'Student Assigned to Deployment',
        'assignment.cancelled' => 'Deployment Assignment Cancelled',
        'assignment.status_changed' => 'Assignment Status Changed',
    ];

    /**
     * Labels for the workflow action recorded on application status history.
     *
     * @var array<string, string>
     */
    private const STATUS_ACTIONS = [
        'submit' => 'Submitted',
        'resubmit' => 'Resubmitted',
        'withdraw' => 'Withdrawn',
        'start_review' => 'Review Started',
        'request_documents' => 'Additional Documents Requested',
        'return_for_correction' => 'Returned for Correction',
        'approve' => 'Approved',
        'reject' => 'Rejected',
        'schedule_deployment' => 'Scheduled for Deployment',
        'deploy' => 'Deployed',
        'complete' => 'Completed',
        'assignment_cancelled' => 'Deployment Assignment Cancelled',
    ];

    /**
     * Label for an audit_logs.action value.
     */
    public static function audit(string $action): string
    {
        return self::AUDIT_ACTIONS[$action]
            ?? ucwords(str_replace(['.', '_'], ' ', $action));
    }

    /**
     * Label for an application status-history entry. Falls back to the
     * human status label when no workflow action was recorded (legacy rows).
     */
    public static function status(?string $action, string $statusLabel): string
    {
        return $action === null
            ? $statusLabel
            : (self::STATUS_ACTIONS[$action] ?? $statusLabel);
    }
}
