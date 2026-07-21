<?php

namespace App\Http\Controllers\v1\Customer\Recognition;

use App\Helpers\GeneralHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Responser\JsonResponser;
use App\Services\Recognition\RecognitionService;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;

#[Group('Customer Recognition', "The authenticated donor's own standing on the Recognition Wall (BRD REC-01/REC-02).")]
class RecognitionController extends Controller
{
    public function __construct(private readonly RecognitionService $recognitionService) {}

    /**
     * My rank
     *
     * Returns the authenticated donor's current rank, tier, lifetime NGN total, and progress
     * to the next tier. Rank is null until they have at least one successful NGN donation.
     */
    #[Endpoint('My rank')]
    #[Authenticated]
    #[Response(status: 200, content: [
        'error' => false,
        'message' => 'Rank retrieved.',
        'data' => [
            'rank' => 7,
            'tier' => 'Gold Sponsor',
            'total' => '3900000.00',
            'total_formatted' => 'NGN 3,900,000.00',
            'next_tier' => 'Emerald Executive',
            'amount_to_next_tier' => '1100000.00',
            'amount_to_next_tier_formatted' => 'NGN 1,100,000.00',
        ],
    ], description: "The donor's rank, tier, and progress to the next tier.")]
    public function me(Request $request)
    {
        try {
            $user = $this->requireCustomer($request);
            $rank = $this->recognitionService->myRank($user);

            return JsonResponser::send(false, 'Rank retrieved.', $rank, 200);
        } catch (\Throwable $th) {
            return GeneralHelper::handleControllerThrowable($th, 'Customer\Recognition\RecognitionController@me');
        }
    }

    private function requireCustomer(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403, 'Forbidden.');
        }

        return $user;
    }
}
