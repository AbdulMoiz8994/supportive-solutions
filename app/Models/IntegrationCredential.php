<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationCredential extends Model
{
    public const KEY_CHAMPS = 'champs';

    public const KEY_MDHHS = 'mdhhs';

    public const KEY_SIGMA = 'sigma';

    public const KEY_ICHAT = 'ichat';

    public const KEY_AVAILITY = 'availity';

    public const KEY_ACCOUNTANTSWORLD = 'accountantsworld';

    public const KEY_HHA = 'hha';

    public const KEY_RINGCENTRAL = 'ringcentral';

    public const KEY_GOOGLE_WORKSPACE = 'google_workspace';

    public const KEY_DOCUSIGN = 'docusign';

    protected $fillable = [
        'key',
        'username',
        'password',
        'api_key',
        'metadata',
    ];

    protected $casts = [
        'username' => 'encrypted',
        'password' => 'encrypted',
        'api_key' => 'encrypted',
        'metadata' => 'encrypted:array',
    ];

    /**
     * @return array<string, string>
     */
    public static function supportedKeys(): array
    {
        return [
            self::KEY_CHAMPS => 'CHAMPS (MiLogin)',
            self::KEY_MDHHS => 'MDHHS Portal',
            self::KEY_SIGMA => 'Sigma (DHS)',
            self::KEY_ICHAT => 'iChat',
            self::KEY_AVAILITY => 'Availity',
            self::KEY_ACCOUNTANTSWORLD => 'AccountantsWorld',
            self::KEY_HHA => 'HHAeXchange',
            self::KEY_RINGCENTRAL => 'RingCentral (Phone/eFax)',
            self::KEY_GOOGLE_WORKSPACE => 'Google Workspace (Email)',
            self::KEY_DOCUSIGN => 'DocuSign (e-Sign)',
        ];
    }
}
