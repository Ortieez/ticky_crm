<?php

namespace OCA\StickyCRM\DB;

use OCP\AppFramework\Db\Entity;
use JsonSerializable;

/**
 * @method string|null getUuid()
 * @method string|null getClientNumber()
 * @method string|null getName()
 * @method string|null getType()
 * @method string|null getStatus()
 * @method string|null getAssignedTo()
 * @method string|null getTier()
 * @method string|null getCategories()
 * @method string|null getEmails()
 * @method string|null getPhones()
 * @method string|null getCustomFields()
 * @method string|null getContactEmail()
 * @method string|null getInvoiceEmail()
 * @method string|null getPhone()
 * @method string|null getWebsite()
 * @method string|null getVatId()
 * @method string|null getTaxNumber()
 * @method string|null getRegisterCourt()
 * @method string|null getRegisterNumber()
 * @method int|string|null getNcFileId()
 * @method string|null getNcFolderPath()
 * @method array getAddresses()
 * @method \DateTime|null getCreatedAt()
 * @method \DateTime|null getUpdatedAt()
 */
class Client extends Entity implements JsonSerializable {
    protected $uuid;
    protected $clientNumber;
    protected $name;
    protected $type;
    protected $status;
    protected $assignedTo;
    protected $tier;
    protected $categories;
    protected $emails;
    protected $phones;
    protected $customFields;
    protected $contactEmail;
    protected $invoiceEmail;
    protected $phone;
    protected $website;
    protected $vatId;
    protected $taxNumber;
    protected $registerCourt;
    protected $registerNumber;
    protected $ncFileId;
    protected $ncFolderPath;
    protected $addresses = [];
    protected $notes = [];
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
    }

    private function decodeJsonArray(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function jsonSerialize(): array {
        $emails = $this->decodeJsonArray($this->getEmails());
        if (empty($emails)) {
            if (!empty($this->getContactEmail())) {
                $emails[] = ['label' => 'Primary', 'email' => $this->getContactEmail()];
            }
            if (!empty($this->getInvoiceEmail()) && $this->getInvoiceEmail() !== $this->getContactEmail()) {
                $emails[] = ['label' => 'Billing', 'email' => $this->getInvoiceEmail()];
            }
        }

        $phones = $this->decodeJsonArray($this->getPhones());
        if (empty($phones) && !empty($this->getPhone())) {
            $phones[] = ['label' => 'Primary', 'phone' => $this->getPhone()];
        }

        $categories = $this->decodeJsonArray($this->getCategories());
        $customFields = $this->decodeJsonArray($this->getCustomFields());

        return [
            'id' => $this->getId(),
            'uuid' => $this->getUuid(),
            'client_number' => $this->getClientNumber(),
            'name' => $this->getName(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'assigned_to' => $this->getAssignedTo(),
            'tier' => $this->getTier(),
            'categories' => array_values($categories),
            'emails' => array_values($emails),
            'phones' => array_values($phones),
            'custom_fields' => (object)$customFields,
            'contact_email' => $this->getContactEmail() ?: ($emails[0]['email'] ?? null),
            'invoice_email' => $this->getInvoiceEmail(),
            'phone' => $this->getPhone() ?: ($phones[0]['phone'] ?? null),
            'website' => $this->getWebsite(),
            'vat_id' => $this->getVatId(),
            'tax_number' => $this->getTaxNumber(),
            'register_court' => $this->getRegisterCourt(),
            'register_number' => $this->getRegisterNumber(),
            'nc_file_id' => $this->getNcFileId(),
            'nc_folder_path' => $this->getNcFolderPath(),
            'addresses' => $this->getAddresses(),
            'notes' => $this->getNotes() ?? [],
            'created_at' => $this->getCreatedAt() ? $this->getCreatedAt()->format(\DateTime::ATOM) : null,
            'updated_at' => $this->getUpdatedAt() ? $this->getUpdatedAt()->format(\DateTime::ATOM) : null,
        ];
    }
}