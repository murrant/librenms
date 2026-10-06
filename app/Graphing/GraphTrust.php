<?php

namespace App\Graphing;

/**
 * The mechanism that established trust for graph access without a user.
 * Adding a case adds a way to render graphs without permission checks, review accordingly.
 */
enum GraphTrust: string
{
    /** AuthenticateGraph verified a signed url */
    case SignedUrl = 'signed-url';
    /** AuthenticateGraph allowed the client by allow_unauth_graphs or allow_unauth_graphs_cidr */
    case UnauthGraphs = 'unauth-graphs';
    /** Alert transports rendering graphs embedded in admin controlled alert templates */
    case Alert = 'alert';
}
