<?php

namespace App\Http\Controllers\API\v1\Auth;

use App\Events\Mails\SendEmailVerification;
use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AfterVerifyRequest;
use App\Http\Requests\Auth\PhoneVerifyRequest;
use App\Http\Requests\Auth\ReSendVerifyRequest;
use App\Http\Resources\UserResource;
use App\Models\Notification;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\AuthService\AuthByMobilePhone;
use App\Services\UserServices\UserWalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

class VerifyAuthController extends Controller
{
    use ApiResponse, \App\Traits\Notification;

    public function verifyPhone(PhoneVerifyRequest $request): JsonResponse
    {
        return (new AuthByMobilePhone)->confirmOPTCode($request->all());
    }

    public function resendVerify(ReSendVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))
            ->whereNotNull('verify_token')
            ->whereNull('email_verified_at')
            ->first();

        if (!$user) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        event((new SendEmailVerification($user)));

        return $this->successResponse('Verify code send');
    }

    public function verifyEmail(?string $verifyToken): JsonResponse
    {
        $user = User::withTrashed()->where('verify_token', $verifyToken)
            ->whereNull('email_verified_at')
            ->first();

        if (empty($user)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        try {
            $user->update([
                'email_verified_at' => now(),
                'deleted_at'        => null,
            ]);

            return $this->successResponse('Email successfully verified', [
                'email' => $user->email
            ]);
        } catch (Throwable) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_501]);
        }
    }

    public function afterVerifyEmail(AfterVerifyRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        if (empty($user)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        $user->update([
            'firstname' => $request->input('firstname', $user->email),
            'lastname'  => $request->input('lastname', $user->lastname),
            'referral'  => $request->input('referral', $user->referral),
            'gender'    => $request->input('gender','male'),
            'password'  => bcrypt($request->input('password', 'password')),
        ]);

        $referral = User::where('my_referral', $request->input('referral', $user->referral))
            ->first();

        if (!empty($referral) && !empty($referral->firebase_token)) {
            $this->sendNotification(
                is_array($referral->firebase_token) ? $referral->firebase_token : [$referral->firebase_token],
                "Congratulations! By your referral registered new user. $user->name_or_email",
                $referral->id,
                [
                    'id'   => $referral->id,
                    'type' => PushNotification::NEW_USER_BY_REFERRAL
                ],
                [$referral->id]
            );
        }

        $id = Notification::where('type', Notification::PUSH)->select(['id', 'type'])->first()?->id;

        if ($id) {
            $user->notifications()->sync([$id]);
        } else {
            $user->notifications()->forceDelete();
        }

        $user->emailSubscription()->updateOrCreate([
            'user_id' => $user->id
        ], [
            'active' => true
        ]);

        if(empty($user->wallet)) {
            $user = (new UserWalletService)->create($user);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return $this->successResponse(__('web.user_successfully_registered'), [
            'token' => $token,
            'user'  => UserResource::make($user),
        ]);
    }

}
