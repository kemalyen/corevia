<?php

namespace App\Http\Middleware;

use App\Portal\VendorAccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorPortalAccess
{
    public function __construct(private VendorAccountContext $context) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $this->context->resolve($request->user());
        $request->user()?->setRelation('account', $account);
        $previousAccountId = $request->session()->get('portal_account_id');

        if ($previousAccountId !== null && (int) $previousAccountId !== $account->getKey()) {
            $request->session()->forget('portal_order_draft');
        }

        $request->session()->put('portal_account_id', $account->getKey());

        return $next($request);
    }
}
