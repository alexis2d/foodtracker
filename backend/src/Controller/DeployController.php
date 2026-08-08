<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The OVH shared-hosting MySQL database is only reachable from within OVH's
 * own network — neither a developer's machine nor a GitHub Actions runner
 * can connect to it directly. This endpoint lets CI trigger migrations by
 * making an ordinary HTTPS request to the already-deployed app instead,
 * which *is* running inside that network. Guarded by a shared-secret
 * token (DEPLOY_TOKEN), not by user auth — there is no user in this flow.
 */
#[Route('/api/internal')]
final class DeployController extends AbstractController
{
    /**
     * Deploys upload source over FTP but never touch var/ (excluded in
     * deploy.yml), so the compiled container + router cache from the
     * previous deploy survives untouched. Without rebuilding it, newly
     * added routes/services/controllers stay invisible (404) even though
     * the code and the DB schema are already up to date.
     *
     * This has to be its own request, run *before* /migrate, rather than
     * both commands sharing one Application in a single request:
     * cache:clear rebuilds the compiled container under a new cache
     * directory and deletes the old one, but the current process is still
     * bound to the old one, so anything it lazily loads afterwards (e.g.
     * running the migration next) blows up. A separate HTTP request means
     * a fresh PHP process/kernel that reads the newly-built cache cleanly.
     */
    #[Route('/clear-cache', name: 'app_internal_clear_cache', methods: ['POST'])]
    public function clearCache(
        Request $request,
        KernelInterface $kernel,
        #[Autowire(env: 'DEPLOY_TOKEN')] string $deployToken,
    ): JsonResponse {
        $unauthorized = $this->checkToken($request, $deployToken);
        if (null !== $unauthorized) {
            return $unauthorized;
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput([
            'command' => 'cache:clear',
            '--no-interaction' => true,
        ]), $output);

        return $this->json([
            'success' => 0 === $exitCode,
            'output' => $output->fetch(),
        ], 0 === $exitCode ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * The OVH shared-hosting MySQL database is only reachable from within
     * OVH's own network — neither a developer's machine nor a GitHub
     * Actions runner can connect to it directly. This endpoint lets CI
     * trigger migrations by making an ordinary HTTPS request to the
     * already-deployed app instead, which *is* running inside that
     * network. Guarded by a shared-secret token (DEPLOY_TOKEN), not by
     * user auth — there is no user in this flow.
     */
    #[Route('/migrate', name: 'app_internal_migrate', methods: ['POST'])]
    public function migrate(
        Request $request,
        KernelInterface $kernel,
        #[Autowire(env: 'DEPLOY_TOKEN')] string $deployToken,
    ): JsonResponse {
        $unauthorized = $this->checkToken($request, $deployToken);
        if (null !== $unauthorized) {
            return $unauthorized;
        }

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $exitCode = $application->run(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--no-interaction' => true,
            '--allow-no-migration' => true,
        ]), $output);

        return $this->json([
            'success' => 0 === $exitCode,
            'output' => $output->fetch(),
        ], 0 === $exitCode ? Response::HTTP_OK : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function checkToken(Request $request, string $deployToken): ?JsonResponse
    {
        $providedToken = (string) $request->headers->get('X-Deploy-Token', '');

        if ('' === $deployToken || !hash_equals($deployToken, $providedToken)) {
            return $this->json(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return null;
    }
}
