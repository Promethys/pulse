<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse as FortifySuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

final class SuccessfulPasswordResetLinkRequestResponse extends FortifySuccessfulPasswordResetLinkRequestResponse
{
    /** @param  Request  $request */
    public function toResponse($request): Response
    {
        $request->attributes->set('password_reset_link_sent', true);

        return parent::toResponse($request);
    }
}
