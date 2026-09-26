<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

/**
 * ID Token の署名鍵を生成して .env に書き込む
 */
class GenerateSigningKey extends Command {
    protected $signature = 'chreeid:generate-key {--show : .env に書かず表示するだけ}';

    protected $description = 'ID Token の署名鍵 (RS256) を生成する';

    /**
     * @return int
     */
    public function handle(): int {
        $pem = $this->generatePem();
        if ($pem === null) return self::FAILURE;

        $encoded = base64_encode($pem);

        if ($this->option('show')) {
            $this->line($encoded);

            return self::SUCCESS;
        }

        // 既存の鍵を黙って差し替えると、発行済みの ID Token が全部検証できなくなる
        $existing = config('chreeid.signing_key');
        if (is_string($existing) && $existing !== '' && !$this->confirm('既に鍵があります。差し替えますか?')) {
            return self::SUCCESS;
        }

        $this->writeToEnv($encoded);
        $this->info('CHREEID_SIGNING_KEY を .env に書き込みました');

        return self::SUCCESS;
    }

    /**
     * @return string|null 失敗したら理由を出力して null
     */
    private function generatePem(): ?string {
        $config = $this->tempOpensslConfig();

        try {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $config,
            ]);

            $pem = '';
            if ($key === false || !openssl_pkey_export($key, $pem, null, ['config' => $config])) {
                while ($message = openssl_error_string()) $this->error($message);

                return null;
            }
        } finally {
            @unlink($config);
        }

        if (is_string($pem)) return $pem;

        $this->error('秘密鍵を PEM として取り出せませんでした');

        return null;
    }

    /**
     * openssl.cnf を同梱していない PHP でも鍵を作れるように、最小の設定を一時ファイルで渡す。
     * Windows の PHP では標準の設定ファイルが見つからず失敗することがある。
     *
     * @return string 一時ファイルのパス
     * @throws RuntimeException 一時ファイルを作れない場合
     */
    private function tempOpensslConfig(): string {
        $path = tempnam(sys_get_temp_dir(), 'chreeid-openssl-');
        if ($path === false) throw new RuntimeException('一時ファイルを作れません');

        file_put_contents($path, "[req]\ndistinguished_name = req_distinguished_name\n[req_distinguished_name]\n");

        return $path;
    }

    /**
     * @param string $encoded base64 化した PEM
     * @throws RuntimeException .env を読み書きできない場合
     */
    private function writeToEnv(string $encoded): void {
        $path = base_path('.env');
        $contents = file_get_contents($path);
        if ($contents === false) throw new RuntimeException('.env を読めません');

        $line = 'CHREEID_SIGNING_KEY=' . $encoded;
        $replaced = preg_replace('/^CHREEID_SIGNING_KEY=.*$/m', $line, $contents);

        if ($replaced === null || $replaced === $contents) {
            $replaced = rtrim($contents) . PHP_EOL . $line . PHP_EOL;
        }

        file_put_contents($path, $replaced);
    }
}
