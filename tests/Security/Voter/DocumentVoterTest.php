<?php

namespace App\Tests\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use App\Security\Voter\DocumentVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class DocumentVoterTest extends TestCase
{
    private DocumentVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new DocumentVoter();
    }

    public function testOwnerCanViewOwnActiveDocument(): void
    {
        $user = $this->createUser(['ROLE_USER'], 1);
        $document = $this->createDocument($user, Document::TYPE_CONTRAT);

        $result = $this->vote($user, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testOwnerCannotViewArchivedDocument(): void
    {
        $user = $this->createUser(['ROLE_USER'], 1);
        $document = $this->createDocument($user, Document::TYPE_CONTRAT);
        $document->markAsArchived('archived', 5);

        $result = $this->vote($user, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAdminCanViewArchivedDocumentWithDedicatedAttribute(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);
        $document = $this->createDocument($owner, Document::TYPE_CONTRAT);
        $document->markAsArchived('archived', 5);

        $result = $this->vote($admin, $document, DocumentVoter::VIEW_ARCHIVED);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDirectorCannotViewArchivedDocumentWithoutAdminRights(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $director = $this->createUser(['ROLE_DIRECTOR'], 2);
        $document = $this->createDocument($owner, Document::TYPE_PAYSLIP);
        $document->markAsArchived('archived', 5);

        $result = $this->vote($director, $document, DocumentVoter::VIEW_ARCHIVED);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDirectorCannotViewSensitiveRib(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $director = $this->createUser(['ROLE_DIRECTOR'], 2);
        $document = $this->createDocument($owner, Document::TYPE_RIB);

        $result = $this->vote($director, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAdminCanViewSensitiveRib(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);
        $document = $this->createDocument($owner, Document::TYPE_RIB);

        $result = $this->vote($admin, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDirectorCannotViewMedicalCertificate(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $director = $this->createUser(['ROLE_DIRECTOR'], 2);
        $document = $this->createDocument($owner, Document::TYPE_MEDICAL_CERTIFICATE);

        $result = $this->vote($director, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDirectorCannotViewPayslipOfAnotherUser(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $director = $this->createUser(['ROLE_DIRECTOR'], 2);
        $document = $this->createDocument($owner, Document::TYPE_PAYSLIP);

        $result = $this->vote($director, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testOwnerCanViewOwnPayslip(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $document = $this->createDocument($owner, Document::TYPE_PAYSLIP);

        $result = $this->vote($owner, $document, DocumentVoter::VIEW);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testUploadAllowedForSelfAndAdmin(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);

        $selfResult = $this->vote($owner, $owner, DocumentVoter::UPLOAD);
        $adminResult = $this->vote($admin, $owner, DocumentVoter::UPLOAD);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $selfResult);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $adminResult);
    }

    public function testDeleteAllowedOnlyForAdminOnPendingDocument(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);
        $document = $this->createDocument($owner, Document::TYPE_CONTRAT);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($owner, $document, DocumentVoter::DELETE)
        );

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($admin, $document, DocumentVoter::DELETE)
        );

        $document->setStatus(Document::STATUS_VALIDATED);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($admin, $document, DocumentVoter::DELETE)
        );
    }

    public function testArchiveAndRestorePermissions(): void
    {
        $admin = $this->createUser(['ROLE_ADMIN'], 1);
        $document = $this->createDocument($admin, Document::TYPE_CONTRAT);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($admin, $document, DocumentVoter::ARCHIVE)
        );

        $document->markAsArchived('archived', 5);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($admin, $document, DocumentVoter::ARCHIVE)
        );

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($admin, $document, DocumentVoter::RESTORE)
        );
    }

    public function testReplacePermissions(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);
        $document = $this->createDocument($owner, Document::TYPE_CONTRAT);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($owner, $document, DocumentVoter::REPLACE)
        );

        $document->setStatus(Document::STATUS_VALIDATED);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($owner, $document, DocumentVoter::REPLACE)
        );

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($admin, $document, DocumentVoter::REPLACE)
        );
    }

    public function testDirectorCanViewGeneralDocument(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $director = $this->createUser(['ROLE_DIRECTOR'], 2);
        $document = $this->createDocument($owner, Document::TYPE_CONTRAT);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote($director, $document, DocumentVoter::VIEW)
        );
    }

    public function testAdminCannotUseStandardViewOnArchivedDocument(): void
    {
        $owner = $this->createUser(['ROLE_USER'], 1);
        $admin = $this->createUser(['ROLE_ADMIN'], 2);
        $document = $this->createDocument($owner, Document::TYPE_CONTRAT);
        $document->markAsArchived('archived', 5);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->vote($admin, $document, DocumentVoter::VIEW)
        );
    }

    private function createUser(array $roles, int $id): User
    {
        $user = new User();
        $user
            ->setEmail(sprintf('user%d@example.test', $id))
            ->setPassword('password')
            ->setFirstName('User')
            ->setLastName((string) $id)
            ->setRoles($roles);

        $this->setEntityId($user, $id);

        return $user;
    }

    private function createDocument(User $owner, string $type): Document
    {
        $document = new Document();
        $document
            ->setUser($owner)
            ->setType($type)
            ->setStatus(Document::STATUS_PENDING)
            ->setFileName('file.pdf')
            ->setOriginalName('file.pdf')
            ->setMimeType('application/pdf')
            ->setFileSize(1024);

        return $document;
    }

    private function vote(User $user, mixed $subject, string $attribute): int
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return $this->voter->vote($token, $subject, [$attribute]);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
