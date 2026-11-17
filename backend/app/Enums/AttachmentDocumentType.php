<?php

namespace App\Enums;

use App\Enums\Concerns\EnumValues;

enum AttachmentDocumentType: string
{
    use EnumValues;

    case Po = 'po';
    case Invoice = 'invoice';
    case DeliveryNote = 'delivery_note';
    case MeterPhoto = 'meter_photo';
    case WarrantyCertificate = 'warranty_certificate';
    case SignedHandover = 'signed_handover';
    case Evidence = 'evidence';
    case DecisionLetter = 'decision_letter';
    case Other = 'other';
}
