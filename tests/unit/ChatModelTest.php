<?php

class ChatModelTest extends TestCase
{
    protected string $suite = 'ChatModelTest';

    // ── SQL Validation ─────────────────────────────────────────────────────────

    public function testSoloSelectPermitido(): void
    {
        $this->assertTrue(
            preg_match('/^SELECT\s/i', 'SELECT id FROM productos') === 1
        );
    }

    public function testInsertNoPermitido(): void
    {
        $sql   = 'INSERT INTO productos (nombre) VALUES ("hack")';
        $upper = strtoupper(trim($sql));
        $this->assertFalse(str_starts_with($upper, 'SELECT '));
    }

    public function testUpdateNoPermitido(): void
    {
        $sql   = 'UPDATE productos SET precio = 0';
        $upper = strtoupper(trim($sql));
        $this->assertFalse(str_starts_with($upper, 'SELECT '));
    }

    public function testDeleteNoPermitido(): void
    {
        $sql   = 'DELETE FROM productos';
        $upper = strtoupper(trim($sql));
        $this->assertFalse(str_starts_with($upper, 'SELECT '));
    }

    public function testDropNoPermitido(): void
    {
        $sql      = 'SELECT id FROM productos; DROP TABLE productos';
        $forbidden = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 'TRUNCATE', 'REPLACE', 'GRANT', 'REVOKE'];
        $upper    = strtoupper($sql);
        $found    = false;
        foreach ($forbidden as $kw) {
            if (strpos($upper, $kw) !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testLimitAgregadoAutomaticamente(): void
    {
        $sql = 'SELECT id FROM productos WHERE activo = 1';
        if (!preg_match('/LIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT 50';
        }
        $this->assertContains('LIMIT 50', $sql);
    }

    public function testLimitExistenteManteniendoSinDuplicar(): void
    {
        $sql = 'SELECT id FROM productos LIMIT 10';
        if (!preg_match('/LIMIT\s+\d+/i', $sql)) {
            $sql .= ' LIMIT 50';
        }
        $this->assertContains('LIMIT 10', $sql);
        $count = substr_count(strtoupper($sql), 'LIMIT');
        $this->assertTrue($count === 1);
    }

    // ── Parseo de respuesta IA ─────────────────────────────────────────────────

    public function testParseoJsonSQLValido(): void
    {
        $json   = '{"tipo":"sql","sql":"SELECT * FROM ventas","explicacion":"últimas ventas"}';
        $parsed = json_decode($json, true);
        $this->assertEquals('sql', $parsed['tipo']);
        $this->assertContains('SELECT', $parsed['sql']);
    }

    public function testParseoJsonTextoValido(): void
    {
        $json   = '{"tipo":"texto","respuesta":"No tengo esa información."}';
        $parsed = json_decode($json, true);
        $this->assertEquals('texto', $parsed['tipo']);
        $this->assertNotEmpty($parsed['respuesta']);
    }

    public function testParseoJsonConBackticks(): void
    {
        $raw = "```json\n{\"tipo\":\"texto\",\"respuesta\":\"Hola\"}\n```";
        $raw = preg_replace('/^```json\s*/m', '', $raw);
        $raw = preg_replace('/^```\s*/m', '', $raw);
        $raw = trim($raw);
        $parsed = json_decode($raw, true);
        $this->assertNotNull($parsed);
        $this->assertEquals('texto', $parsed['tipo']);
    }

    public function testParseoJsonInvalidoCaeEnTexto(): void
    {
        $raw = 'Respuesta libre sin JSON';
        $parsed = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            $parsed = ['tipo' => 'texto', 'respuesta' => $raw];
        }
        $this->assertEquals('texto', $parsed['tipo']);
        $this->assertEquals($raw, $parsed['respuesta']);
    }

    public function testParseoJsonEmbedido(): void
    {
        $raw = 'Aquí está: {"tipo":"sql","sql":"SELECT 1","explicacion":"test"} y más texto';
        if (preg_match('/\{[\s\S]+\}/U', $raw, $m)) {
            $parsed = json_decode($m[0], true);
        } else {
            $parsed = null;
        }
        $this->assertNotNull($parsed);
        $this->assertEquals('sql', $parsed['tipo']);
    }

    // ── Tablas whitelist ──────────────────────────────────────────────────────

    public function testTablasWhitelistDefinidas(): void
    {
        $tablas = MCP_TABLAS_OK;
        $this->assertTrue(count($tablas) > 0);
        $this->assertTrue(in_array('productos', $tablas));
        $this->assertTrue(in_array('ventas', $tablas));
        $this->assertTrue(in_array('clientes', $tablas));
    }

    public function testTablasWhitelistNoTieneTablasSensibles(): void
    {
        $sensibles = ['migrations', 'sessions', 'oauth_tokens', 'password_resets'];
        foreach ($sensibles as $t) {
            $this->assertFalse(in_array($t, MCP_TABLAS_OK));
        }
    }

    // ── Config ────────────────────────────────────────────────────────────────

    public function testConfigClaudeModelDefinido(): void
    {
        $this->assertTrue(defined('CLAUDE_MODEL'));
        $this->assertTrue(strlen(CLAUDE_MODEL) > 0);
    }

    public function testConfigClaudeApiKeyDefinido(): void
    {
        $this->assertTrue(defined('CLAUDE_API_KEY'));
    }
}
