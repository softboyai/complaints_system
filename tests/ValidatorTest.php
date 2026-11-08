<?php
// PHPUnit tests for includes/validator.php
// To run: vendor\bin\phpunit tests\ValidatorTest.php

use PHPUnit\Framework\TestCase;

// Include the validator without PDO for unit tests.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/validator.php';

class ValidatorTest extends TestCase {

    // ---- Name ----

    public function testValidName(): void {
        $r = validate_name("Irakoze Ntwari");
        $this->assertTrue($r['valid']);
    }

    public function testNameTooShort(): void {
        $r = validate_name("A");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('between 2 and 50', $r['error']);
    }

    public function testNameWithNumbers(): void {
        $r = validate_name("John123 Doe");
        $this->assertFalse($r['valid']);
    }

    public function testNameEmpty(): void {
        $r = validate_name("");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('required', $r['error']);
    }

    public function testNameWithHyphen(): void {
        $r = validate_name("Jean-Paul Habimana");
        $this->assertTrue($r['valid']);
    }

    public function testNameWithApostrophe(): void {
        $r = validate_name("O'Brien");
        $this->assertTrue($r['valid']);
    }

    // ---- Registration number ----

    public function testValidRegNumber(): void {
        $r = validate_reg_number("BBICTR/2024/45081", $this->createMockPDO(false));
        $this->assertTrue($r['valid']);
    }

    public function testRegNumberTooShort(): void {
        $r = validate_reg_number("ABC", $this->createMockPDO(false));
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('between 6 and 20', $r['error']);
    }

    public function testRegNumberWithSpecialChars(): void {
        $r = validate_reg_number("REG@123", $this->createMockPDO(false));
        $this->assertFalse($r['valid']);
    }

    // ---- Email ----

    public function testValidEmail(): void {
        $r = validate_email("irakoze@students.mku.ac.rw", $this->createMockPDO(false));
        $this->assertTrue($r['valid']);
    }

    public function testInvalidEmailFormat(): void {
        $r = validate_email("not-an-email", $this->createMockPDO(false));
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('valid email', $r['error']);
    }

    public function testDisposableEmailDomain(): void {
        $r = validate_email("test@mailinator.com", $this->createMockPDO(false));
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('Disposable', $r['error']);
    }

    // ---- Phone ----

    public function testValidPhone(): void {
        $r = validate_phone("+250788123456");
        $this->assertTrue($r['valid']);
    }

    public function testInvalidPhone(): void {
        $r = validate_phone("abc-xyz");
        $this->assertFalse($r['valid']);
    }

    public function testEmptyPhoneIsOptional(): void {
        $r = validate_phone("");
        $this->assertTrue($r['valid']); // Phone is optional
    }

    // ---- Password ----

    public function testStrongPassword(): void {
        $r = validate_password("Secure@2026", "Irakoze Ntwari", "irakoze@test.com");
        $this->assertTrue($r['valid']);
    }

    public function testPasswordTooShort(): void {
        $r = validate_password("Ab@1", "Test", "test@test.com");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('8 characters', $r['error']);
    }

    public function testPasswordNoUppercase(): void {
        $r = validate_password("secure@2026", "Test", "test@test.com");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('uppercase', $r['error']);
    }

    public function testPasswordNoSpecialChar(): void {
        $r = validate_password("Secure2026", "Test", "test@test.com");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('special character', $r['error']);
    }

    public function testPasswordContainsName(): void {
        $r = validate_password("Irakoze@2026", "Irakoze Ntwari", "irakoze@test.com");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('name', $r['error']);
    }

    public function testPasswordWithSpace(): void {
        $r = validate_password("Secure @2026", "Test", "test@test.com");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('spaces', $r['error']);
    }

    // ---- Complaint fields ----

    public function testValidComplaintTitle(): void {
        $r = validate_complaint_title("Missing marks in Database Systems exam");
        $this->assertTrue($r['valid']);
    }

    public function testComplaintTitleTooShort(): void {
        $r = validate_complaint_title("Hi");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('between 5 and 100', $r['error']);
    }

    public function testValidComplaintDescription(): void {
        $r = validate_complaint_description("I sat the exam on 15 October and my marks were not recorded correctly despite attending and submitting.");
        $this->assertTrue($r['valid']);
    }

    public function testComplaintDescriptionTooShort(): void {
        $r = validate_complaint_description("Short");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('between 20 and 2000', $r['error']);
    }

    // ---- Password match ----

    public function testPasswordsMatch(): void {
        $r = validate_passwords_match("Password@1", "Password@1");
        $this->assertTrue($r['valid']);
    }

    public function testPasswordsDoNotMatch(): void {
        $r = validate_passwords_match("Password@1", "Different@2");
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('do not match', $r['error']);
    }

    // ---- Helper: Mock PDO that returns no existing row ----

    private function createMockPDO(bool $rowExists): PDO {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($rowExists ? ['id' => 1] : false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }
}
