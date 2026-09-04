<?php

declare(strict_types=1);

namespace DmmApiClient\LiveProbe;

use DmmApiClient\SiteCode;

/**
 * `FloorList` のレスポンスから、フロアの一覧を取り出す。
 *
 * DTO ではなくデコード済みの配列から読むのは、`FloorList` 自体の検証に失敗しても
 * 後続の取得を進めたいため。読めなかった箇所は警告にして飛ばす。
 */
final readonly class FloorCatalog
{
    /**
     * @param list<FloorRef> $floors
     * @param list<string>   $warnings 読み飛ばした箇所の説明
     */
    private function __construct(
        public array $floors,
        public array $warnings,
    ) {
    }

    /**
     * @param array<mixed> $decoded
     */
    public static function fromDecoded(array $decoded): self
    {
        $result = $decoded['result'] ?? null;
        $sites = is_array($result) ? ($result['site'] ?? null) : null;

        if (! is_array($sites)) {
            return new self([], ['result.site is missing or not an array.']);
        }

        $floors = [];
        $warnings = [];

        foreach ($sites as $siteIndex => $site) {
            if (! is_array($site)) {
                $warnings[] = sprintf('result.site.%s is not an object.', (string) $siteIndex);

                continue;
            }

            $siteCodeValue = self::string($site, 'code');
            $siteCode = $siteCodeValue === null ? null : SiteCode::tryFrom($siteCodeValue);

            if ($siteCode === null) {
                $warnings[] = sprintf(
                    'result.site.%s has an unknown site code "%s"; its floors are skipped.',
                    (string) $siteIndex,
                    $siteCodeValue ?? '',
                );

                continue;
            }

            $services = $site['service'] ?? null;

            if (! is_array($services)) {
                $warnings[] = sprintf('result.site.%s.service is missing or not an array.', (string) $siteIndex);

                continue;
            }

            foreach ($services as $serviceIndex => $service) {
                $path = sprintf('result.site.%s.service.%s', (string) $siteIndex, (string) $serviceIndex);

                if (! is_array($service)) {
                    $warnings[] = $path . ' is not an object.';

                    continue;
                }

                $serviceCode = self::string($service, 'code');

                if ($serviceCode === null) {
                    $warnings[] = $path . '.code is missing.';

                    continue;
                }

                $items = $service['floor'] ?? null;

                if (! is_array($items)) {
                    $warnings[] = $path . '.floor is missing or not an array.';

                    continue;
                }

                foreach ($items as $floorIndex => $floor) {
                    $floorPath = sprintf('%s.floor.%s', $path, (string) $floorIndex);

                    if (! is_array($floor)) {
                        $warnings[] = $floorPath . ' is not an object.';

                        continue;
                    }

                    $id = self::string($floor, 'id');
                    $code = self::string($floor, 'code');

                    if ($id === null || $code === null) {
                        $warnings[] = $floorPath . ' has no usable id/code.';

                        continue;
                    }

                    $floors[] = new FloorRef(
                        site: $siteCode,
                        siteName: self::string($site, 'name') ?? '',
                        serviceCode: $serviceCode,
                        serviceName: self::string($service, 'name') ?? '',
                        floorId: $id,
                        floorCode: $code,
                        floorName: self::string($floor, 'name') ?? '',
                    );
                }
            }
        }

        return new self($floors, $warnings);
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return is_int($value) ? (string) $value : null;
    }
}
