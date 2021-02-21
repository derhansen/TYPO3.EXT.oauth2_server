<?php

namespace R3H6\Oauth2Server\Tests\Unit;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Http\Message\ServerRequestInterface;
use R3H6\Oauth2Server\Configuration\Configuration;
use R3H6\Oauth2Server\Controller\AuthorizationController;
use R3H6\Oauth2Server\Domain\Repository\AccessTokenRepository;
use R3H6\Oauth2Server\Domain\Repository\UserRepository;
use R3H6\Oauth2Server\Http\RequestAttribute;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AuthorizationControllerTest extends UnitTestCase
{
    use \Prophecy\PhpUnit\ProphecyTrait;

    /**
     * @var AuthorizationController
     */
    private $subject;

    /**
     * @var AuthorizationServer
     */
    private $server;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var AccessTokenRepository
     */
    private $accessTokenRepository;

    protected $resetSingletonInstances = true;

    public function setUp(): void
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'aa06c08658128b1247afeb704b26475edfa8b70afb5369ea66bb7a8098950cdb75b7ec73140a352b6fb51aa5b9f69042';

        $this->server = $this->prophesize(AuthorizationServer::class);
        $this->userRepository = $this->prophesize(UserRepository::class);
        $this->accessTokenRepository = $this->prophesize(AccessTokenRepository::class);

        $this->subject = new AuthorizationController($this->userRepository->reveal(), $this->accessTokenRepository->reveal(), $this->server->reveal());
    }

    /**
     * @test
     */
    public function startAuthorizationWillReturnRedirectToConsent()
    {
        $frontenUser = $this->prophesize(FrontendUserAuthentication::class);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute('frontend.user')->willReturn($frontenUser->reveal());

        $configuration = $this->prophesize(Configuration::class);
        $configuration->getConsentPageUid()->willReturn(0);
        $request->getAttribute(RequestAttribute::CONFIGURATION)->willReturn($configuration->reveal());

        $site = $this->prophesize(Site::class);
        $request->getAttribute('site')->willReturn($site->reveal());

        $request->getUri()->willReturn(new Uri('http://localhost/'));

        $authRequest = $this->prophesize(AuthorizationRequest::class);
        $this->server->validateAuthorizationRequest($request->reveal())->willReturn($authRequest->reveal());

        $response = $this->subject->startAuthorization($request->reveal());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertMatchesRegularExpression('#/\?_=#', $response->getHeader('Location')[0]);
        $frontenUser->setAndSaveSessionData('oauth2/authRequest', $authRequest->reveal())->shouldHaveBeenCalled();
    }
}
