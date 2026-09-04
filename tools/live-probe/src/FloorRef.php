<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\SiteCode;

/**
 * `FloorList` から取り出した、1 つのフロアを指す座標。
 */
final readonly class FloorRef
{
    public function __construct(
        public SiteCode $site,
        public string $siteName,
        public string $serviceCode,
        public string $serviceName,
        public string $floorId,
        public string $floorCode,
        public string $floorName,
    ) {
    }

    /**
     * 保存するファイル名の先頭に付ける、フロアを表す文字列。
     *
     * 例: `FANZA__digital__videoa-43`
     */
    public function key(): string
    {
        return Target::sanitize($this->site->value)
            . '__' . Target::sanitize($this->serviceCode)
            . '__' . Target::sanitize($this->floorCode) . '-' . Target::sanitize($this->floorId);
    }

    /**
     * manifest に残す、このフロアの内訳。
     *
     * @return array<string, string>
     */
    public function context(): array
    {
        return [
            'site' => $this->site->value,
            'service' => $this->serviceCode,
            'floor' => $this->floorCode,
            'floor_id' => $this->floorId,
        ];
    }
}
