<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\StoreInterviewMeetingRequest;
use App\Http\Requests\Tenant\HR\UpdateInterviewMeetingRequest;
use App\Http\Resources\Tenant\HR\InterviewMeetingResource;
use App\Models\HR\Interview;
use App\Models\HR\InterviewMeeting;
use App\Services\Tenant\HR\Meetings\InterviewMeetingService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Provider-backed interview meetings nested under an interview.
 */
#[Group('HR / Recruitment / Interviews')]
class InterviewMeetingController extends Controller
{
    public function __construct(private readonly InterviewMeetingService $meetings) {}

    #[Response(status: 201, description: 'Created interview meeting.', type: 'array{success: true, message: string, data: InterviewMeetingResource, meta: null, errors: null}')]
    public function store(StoreInterviewMeetingRequest $request, Interview $interview): JsonResponse
    {
        $this->authorize('create', [InterviewMeeting::class, $interview]);

        $data = $request->validated();
        $recreate = (bool) ($data['recreate'] ?? false);
        unset($data['recreate']);

        $meeting = $recreate
            ? $this->meetings->recreateForInterview($interview, $data)
            : $this->meetings->createForInterview($interview, $data);

        return $this->created(
            new InterviewMeetingResource($meeting),
            'Interview meeting created successfully.',
        );
    }

    #[Response(status: 200, description: 'Updated interview meeting.', type: 'array{success: true, message: string, data: InterviewMeetingResource, meta: null, errors: null}')]
    public function update(UpdateInterviewMeetingRequest $request, Interview $interview): JsonResponse
    {
        $this->authorize('update', [InterviewMeeting::class, $interview]);

        return $this->updated(
            new InterviewMeetingResource($this->meetings->updateForInterview($interview, $request->validated())),
            'Interview meeting updated successfully.',
        );
    }

    #[Response(status: 200, description: 'Cancelled interview meeting.', type: 'array{success: true, message: string, data: null, meta: null, errors: null}')]
    public function destroy(Interview $interview): JsonResponse
    {
        $this->authorize('delete', [InterviewMeeting::class, $interview]);

        $this->meetings->cancelForInterview($interview, force: true);

        return $this->deleted('Interview meeting cancelled successfully.');
    }
}
