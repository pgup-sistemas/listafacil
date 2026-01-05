# 📊 Sistema de Rastreamento de Origem de Doações

## 📋 Sumário Executivo

### Visão Geral
Sistema de rastreamento UTM (Urchin Tracking Module) que identifica automaticamente a origem de cada doação, permitindo métricas detalhadas de efetividade por unidade, canal e administrador.

### Objetivo Principal
Resolver o problema de links de campanhas circulando no WhatsApp entre múltiplas unidades sem identificação de origem, fornecendo:
- **Transparência** para doadores (sabem onde estão doando)
- **Analytics** para admins (medem efetividade de divulgação)
- **Atribuição** correta de doações por unidade

### Proposta de Valor
```
"Rastreie automaticamente, atribua corretamente, meça efetivamente."
```

---

## 🎯 Problema Crítico Identificado

### Situação Atual
**Problema:**
- Links de campanhas circulam no WhatsApp entre múltiplas unidades
- Mesmo token (`/c/abc12345`) é compartilhado por diferentes unidades
- Doador não sabe a qual unidade está doando de fato
- Admin não consegue rastrear qual unidade gerou a doação
- Impossível medir ROI de divulgação por unidade/admin

**Exemplo Real:**
```
Unidade SP compartilha: listafacil.com/c/abc12345
Unidade RJ compartilha: listafacil.com/c/abc12345 (mesmo link!)
Unidade DF compartilha: listafacil.com/c/abc12345 (mesmo link!)

Doador recebe de múltiplas fontes e não sabe qual é "sua" unidade.
Admin não consegue medir qual unidade foi mais efetiva na divulgação.
```

### Impacto
- ❌ **Falta de transparência** para doadores
- ❌ **Impossível medir efetividade** de divulgação
- ❌ **Atribuição incorreta** de doações
- ❌ **Sem dados** para tomada de decisão
- ❌ **Desmotivação** de admins que mais divulgam

---

## 💡 Solução Proposta: UTM Tracking

### Conceito
Adicionar parâmetros UTM à URL de compartilhamento que identificam automaticamente:
- **Origem**: Qual unidade compartilhou
- **Canal**: Como foi compartilhado (WhatsApp, QR Code, e-mail)
- **Admin**: Quem compartilhou (opcional)

### Formato da URL
```
ANTES (sem rastreamento):
listafacil.com/c/abc12345

DEPOIS (com rastreamento):
listafacil.com/c/abc12345?origem=unidade_sp&canal=whatsapp&admin=maria
```

### Fluxo de Rastreamento
```
┌─────────────────────────────────────────────────────────────┐
│ 1. ADMIN COMPARTILHA                                        │
├─────────────────────────────────────────────────────────────┤
│ • Admin da Unidade SP clica em "Compartilhar no WhatsApp"  │
│ • Sistema gera URL: /c/abc123?origem=sp&canal=whatsapp     │
│ • Mensagem pronta copiada para clipboard                    │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. DOADOR ACESSA                                            │
├─────────────────────────────────────────────────────────────┤
│ • Doador clica no link recebido                            │
│ • Sistema captura parâmetros UTM automaticamente            │
│ • Salva em sessão: origem=sp, canal=whatsapp               │
│ • Mostra banner: "Você está doando para: Unidade SP"       │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. DOADOR FAZ DOAÇÃO                                        │
├─────────────────────────────────────────────────────────────┤
│ • Doador preenche formulário e confirma                    │
│ • Sistema vincula automaticamente origem da sessão          │
│ • Doação salva com metadados de origem                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. ADMIN VISUALIZA ANALYTICS                                │
├─────────────────────────────────────────────────────────────┤
│ • Dashboard mostra doações por origem                      │
│ • Ranking de admins que mais converteram                   │
│ • Efetividade por canal (WhatsApp vs QR vs Email)          │
└─────────────────────────────────────────────────────────────┘
```

---

## 🗄️ Modelagem de Dados

### Collection: doacoes (Firestore)

#### Campos Adicionados
```javascript
{
  id: "doacao_uuid",
  campanha_id: "camp_natal2025",
  doador_nome: "João Silva",
  valor: 100.00,
  status: "confirmado",
  
  // 🆕 CAMPOS DE RASTREAMENTO
  origem: {
    // Identificação da unidade
    unidade_id: "unidade_sp01",
    unidade_nome: "São Paulo - Centro",
    
    // Canal de compartilhamento
    canal: "whatsapp", // whatsapp | qrcode | email | direto | outro
    
    // Admin que compartilhou (opcional)
    compartilhado_por: "admin_maria",
    compartilhado_por_nome: "Maria Silva",
    
    // Metadados de acesso
    data_acesso: Timestamp,
    ip_origem: "179.x.x.x", // Opcional (LGPD)
    user_agent: "WhatsApp/2.23.20.76 Mozilla/5.0...",
    
    // Referência (se veio de campanha específica)
    campanha_origem_id: "camp_natal2025", // Se diferente da doação
    
    // Localização (opcional, via IP)
    cidade: "São Paulo",
    estado: "SP"
  },
  
  // Campos existentes mantidos
  pin: "A1B2C3",
  tipo: "dinheiro",
  forma_pagamento: "pix",
  comprovante: { ... },
  created_at: Timestamp,
  updated_at: Timestamp
}
```

### Collection: visualizacoes (Nova - Analytics)

```javascript
{
  id: "view_uuid",
  campanha_id: "camp_natal2025",
  
  // Origem do acesso
  origem: {
    unidade_id: "unidade_sp01",
    canal: "whatsapp",
    admin: "admin_maria"
  },
  
  // Metadados
  timestamp: Timestamp,
  ip: "179.x.x.x",
  user_agent: "WhatsApp/...",
  
  // Conversão (preenchido se doar)
  converteu: false,
  doacao_id: null, // Preenchido se converter
  tempo_para_conversao: null // Em segundos
}
```

### Índices Firestore Necessários

```javascript
// Composite Indexes
doacoes:
  - campanha_id ASC, origem.unidade_id ASC, created_at DESC
  - origem.canal ASC, created_at DESC
  - origem.compartilhado_por ASC, created_at DESC
  - origem.unidade_id ASC, status ASC, created_at DESC

visualizacoes:
  - campanha_id ASC, origem.unidade_id ASC, timestamp DESC
  - origem.canal ASC, timestamp DESC
  - converteu ASC, timestamp DESC
```

---

## 🏗️ Arquitetura de Implementação

### Camada de Serviços

#### URLService (Nova)
```php
// app/Services/URLService.php
<?php

namespace App\Services;

use App\Models\Campanha;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class URLService
{
    /**
     * Gera link de compartilhamento com rastreamento UTM
     * 
     * @param Campanha $campanha
     * @param string $canal whatsapp|qrcode|email|direto
     * @param User|null $admin Admin que está compartilhando
     * @return string
     */
    public function gerarLinkCompartilhamento(
        Campanha $campanha,
        string $canal = 'whatsapp',
        ?User $admin = null
    ): string {
        // Parâmetros UTM
        $params = [
            'origem' => $campanha->unidade_id,
            'canal' => $canal,
        ];
        
        // Adiciona admin se fornecido
        if ($admin) {
            $params['admin'] = $admin->id;
        }
        
        // Gera query string
        $query = http_build_query($params);
        
        // Retorna URL completa
        return route('campanha.show', ['token' => $campanha->token]) . '?' . $query;
    }
    
    /**
     * Gera mensagem pronta para WhatsApp
     * 
     * @param Campanha $campanha
     * @return string
     */
    public function gerarMensagemWhatsApp(Campanha $campanha): string
    {
        $url = $this->gerarLinkCompartilhamento(
            $campanha, 
            'whatsapp', 
            Auth::user()
        );
        
        return "🎄 *{$campanha->titulo}*\n\n" .
               "📍 {$campanha->unidade->nome}\n\n" .
               "{$campanha->descricao}\n\n" .
               "👉 *Doe agora:* {$url}";
    }
    
    /**
     * Gera QR Code com rastreamento
     * 
     * @param Campanha $campanha
     * @return string Base64 do QR Code
     */
    public function gerarQRCode(Campanha $campanha): string
    {
        $url = $this->gerarLinkCompartilhamento($campanha, 'qrcode');
        
        return \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)
            ->format('png')
            ->generate($url);
    }
    
    /**
     * Gera link para email
     * 
     * @param Campanha $campanha
     * @param User|null $admin
     * @return string
     */
    public function gerarLinkEmail(Campanha $campanha, ?User $admin = null): string
    {
        return $this->gerarLinkCompartilhamento($campanha, 'email', $admin);
    }
}
```

#### OrigemService (Nova)
```php
// app/Services/OrigemService.php
<?php

namespace App\Services;

use Illuminate\Http\Request;
use App\Models\Campanha;
use Illuminate\Support\Facades\Log;

class OrigemService
{
    /**
     * Captura origem do acesso a partir da URL
     * 
     * @param Request $request
     * @param Campanha $campanha
     * @return array
     */
    public function capturarOrigem(Request $request, Campanha $campanha): array
    {
        // Captura parâmetros UTM ou usa padrão da campanha
        $unidadeId = $request->get('origem', $campanha->unidade_id);
        $canal = $request->get('canal', 'direto');
        $adminId = $request->get('admin');
        
        // Busca informações da unidade
        $unidade = \App\Models\Unidade::find($unidadeId);
        
        // Busca informações do admin (se fornecido)
        $admin = $adminId ? \App\Models\User::find($adminId) : null;
        
        return [
            'unidade_id' => $unidadeId,
            'unidade_nome' => $unidade ? $unidade->nome : 'Não identificada',
            'canal' => $canal,
            'compartilhado_por' => $adminId,
            'compartilhado_por_nome' => $admin ? $admin->nome : null,
            'data_acesso' => now(),
            'ip_origem' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'campanha_origem_id' => $campanha->id,
            // Localização via IP (opcional)
            'cidade' => $this->obterCidadePorIP($request->ip()),
            'estado' => $this->obterEstadoPorIP($request->ip()),
        ];
    }
    
    /**
     * Salva origem na sessão
     * 
     * @param array $origem
     * @return void
     */
    public function salvarOrigemNaSessao(array $origem): void
    {
        session(['origem_acesso' => $origem]);
        
        Log::info('Origem capturada', $origem);
    }
    
    /**
     * Recupera origem da sessão
     * 
     * @return array|null
     */
    public function obterOrigemDaSessao(): ?array
    {
        return session('origem_acesso');
    }
    
    /**
     * Obtém cidade por IP (exemplo - requer serviço externo)
     * 
     * @param string $ip
     * @return string|null
     */
    private function obterCidadePorIP(string $ip): ?string
    {
        // TODO: Integrar com serviço de geolocalização
        // Exemplo: ip-api.com, ipinfo.io, etc
        return null;
    }
    
    /**
     * Obtém estado por IP (exemplo - requer serviço externo)
     * 
     * @param string $ip
     * @return string|null
     */
    private function obterEstadoPorIP(string $ip): ?string
    {
        // TODO: Integrar com serviço de geolocalização
        return null;
    }
}
```

#### VisualizacaoService (Nova)
```php
// app/Services/VisualizacaoService.php
<?php

namespace App\Services;

use App\Models\Campanha;
use Kreait\Firebase\Firestore;

class VisualizacaoService
{
    private $firestore;
    
    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }
    
    /**
     * Registra visualização da campanha
     * 
     * @param Campanha $campanha
     * @param array $origem
     * @return void
     */
    public function registrar(Campanha $campanha, array $origem): void
    {
        $this->firestore->database()
            ->collection('visualizacoes')
            ->add([
                'campanha_id' => $campanha->id,
                'origem' => $origem,
                'timestamp' => new \DateTime(),
                'converteu' => false,
                'doacao_id' => null,
                'tempo_para_conversao' => null
            ]);
    }
    
    /**
     * Marca visualização como convertida
     * 
     * @param string $doacaoId
     * @param array $origem
     * @return void
     */
    public function marcarComoConvertida(string $doacaoId, array $origem): void
    {
        // Busca visualização correspondente
        $snapshot = $this->firestore->database()
            ->collection('visualizacoes')
            ->where('origem.unidade_id', '=', $origem['unidade_id'])
            ->where('origem.canal', '=', $origem['canal'])
            ->where('converteu', '=', false)
            ->orderBy('timestamp', 'DESC')
            ->limit(1)
            ->documents();
        
        if (!$snapshot->isEmpty()) {
            $doc = $snapshot->rows()[0];
            $dataAcesso = $doc->get('timestamp');
            $agora = new \DateTime();
            $tempoConversao = $agora->getTimestamp() - $dataAcesso->getTimestamp();
            
            $doc->reference()->update([
                ['path' => 'converteu', 'value' => true],
                ['path' => 'doacao_id', 'value' => $doacaoId],
                ['path' => 'tempo_para_conversao', 'value' => $tempoConversao]
            ]);
        }
    }
}
```

### Camada de Controllers

#### CampanhaController (Atualizado)
```php
// app/Http/Controllers/CampanhaController.php
<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Services\OrigemService;
use App\Services\VisualizacaoService;
use Illuminate\Http\Request;

class CampanhaController extends Controller
{
    private $origemService;
    private $visualizacaoService;
    
    public function __construct(
        OrigemService $origemService,
        VisualizacaoService $visualizacaoService
    ) {
        $this->origemService = $origemService;
        $this->visualizacaoService = $visualizacaoService;
    }
    
    /**
     * Exibe página pública da campanha
     * 
     * @param Request $request
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function show(Request $request, string $token)
    {
        // Busca campanha
        $campanha = Campanha::where('token', $token)->firstOrFail();
        
        // Verifica se campanha está ativa
        if ($campanha->status !== 'ativa') {
            abort(404, 'Campanha não encontrada ou encerrada.');
        }
        
        // 🆕 Captura origem do acesso
        $origem = $this->origemService->capturarOrigem($request, $campanha);
        
        // 🆕 Salva origem na sessão
        $this->origemService->salvarOrigemNaSessao($origem);
        
        // 🆕 Registra visualização (analytics)
        $this->visualizacaoService->registrar($campanha, $origem);
        
        // Retorna view com origem
        return view('campanha.show', [
            'campanha' => $campanha,
            'origem' => $origem
        ]);
    }
}
```

#### DoacaoController (Atualizado)
```php
// app/Http/Controllers/DoacaoController.php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DoacaoRequest;
use App\Services\DoacaoService;
use App\Services\OrigemService;
use App\Services\VisualizacaoService;
use Illuminate\Http\JsonResponse;

class DoacaoController extends Controller
{
    private $doacaoService;
    private $origemService;
    private $visualizacaoService;
    
    public function __construct(
        DoacaoService $doacaoService,
        OrigemService $origemService,
        VisualizacaoService $visualizacaoService
    ) {
        $this->doacaoService = $doacaoService;
        $this->origemService = $origemService;
        $this->visualizacaoService = $visualizacaoService;
    }
    
    /**
     * Cria nova doação
     * 
     * @param DoacaoRequest $request
     * @return JsonResponse
     */
    public function store(DoacaoRequest $request): JsonResponse
    {
        // 🆕 Recupera origem da sessão
        $origem = $this->origemService->obterOrigemDaSessao();
        
        // Se não houver origem na sessão, usa padrão da campanha
        if (!$origem) {
            $campanha = \App\Models\Campanha::find($request->campanha_id);
            $origem = [
                'unidade_id' => $campanha->unidade_id,
                'unidade_nome' => $campanha->unidade->nome,
                'canal' => 'direto',
                'compartilhado_por' => null,
                'data_acesso' => now()
            ];
        }
        
        // Cria doação com origem
        $doacao = $this->doacaoService->registrarDoacao(
            array_merge($request->validated(), ['origem' => $origem])
        );
        
        // 🆕 Marca visualização como convertida
        $this->visualizacaoService->marcarComoConvertida($doacao->id, $origem);
        
        return response()->json([
            'success' => true,
            'doacao' => $doacao,
            'pin' => $doacao->pin
        ], 201);
    }
}
```

#### CompartilhamentoController (Nova)
```php
// app/Http/Controllers/CompartilhamentoController.php
<?php

namespace App\Http\Controllers;

use App\Models\Campanha;
use App\Services\URLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CompartilhamentoController extends Controller
{
    private $urlService;
    
    public function __construct(URLService $urlService)
    {
        $this->urlService = $urlService;
    }
    
    /**
     * Gera link para WhatsApp
     * 
     * @param Request $request
     * @param Campanha $campanha
     * @return JsonResponse
     */
    public function whatsapp(Request $request, Campanha $campanha): JsonResponse
    {
        $mensagem = $this->urlService->gerarMensagemWhatsApp($campanha);
        
        return response()->json([
            'mensagem' => $mensagem,
            'url' => $this->urlService->gerarLinkCompartilhamento(
                $campanha, 
                'whatsapp', 
                $request->user()
            )
        ]);
    }
    
    /**
     * Gera QR Code
     * 
     * @param Campanha $campanha
     * @return \Illuminate\Http\Response
     */
    public function qrcode(Campanha $campanha)
    {
        $qrcode = $this->urlService->gerarQRCode($campanha);
        
        return response($qrcode)
            ->header('Content-Type', 'image/png');
    }
    
    /**
     * Gera link para email
     * 
     * @param Request $request
     * @param Campanha $campanha
     * @return JsonResponse
     */
    public function email(Request $request, Campanha $campanha): JsonResponse
    {
        return response()->json([
            'url' => $this->urlService->gerarLinkEmail($campanha, $request->user())
        ]);
    }
}
```

#### AnalyticsController (Nova)
```php
// app/Http/Controllers/AnalyticsController.php
<?php

namespace App\Http\Controllers;

use App\Models\Doacao;
use App\Models\Campanha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Firestore;

class AnalyticsController extends Controller
{
    private $firestore;
    
    public function __construct(Firestore $firestore)
    {
        $this->firestore = $firestore;
    }
    
    /**
     * Dashboard de analytics
     * 
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Filtro por unidade (se não for nacional)
        $query = Doacao::query();
        
        if ($user->role !== 'nacional') {
            $query->whereHas('campanha', function($q) use ($user) {
                $q->where('unidade_id', $user->unidade_id);
            });
        }
        
        // Período (padrão: últimos 30 dias)
        $dataInicio = $request->get('data_inicio', now()->subDays(30));
        $dataFim = $request->get('data_fim', now());
        
        $query->whereBetween('created_at', [$dataInicio, $dataFim]);
        
        $doacoes = $query->get();
        
        // Analytics por canal
        $porCanal = $doacoes->groupBy('origem.canal')
            ->map(function($d) {
                return [
                    'total' => $d->count(),
                    'valor' => $d->sum('valor'),
                    'percentual' => 0 // Calculado abaixo
                ];
            });
        
        $totalDoacoes = $doacoes->count();
        foreach ($porCanal as $canal => $stats) {
            $porCanal[$canal]['percentual'] = 
                $totalDoacoes > 0 
                    ? round(($stats['total'] / $totalDoacoes) * 100, 2) 
                    : 0;
        }
        
        // Ranking de admins
        $rankingAdmins = $doacoes
            ->whereNotNull('origem.compartilhado_por')
            ->groupBy('origem.compartilhado_por')
            ->map(function($d) {
                return [
                    'nome' => $d->first()['origem']['compartilhado_por_nome'] ?? 'Não identificado',
                    'total' => $d->count(),
                    'valor' => $d->sum('valor')
                ];
            })
            ->sortByDesc('total')
            ->take(10);
        
        // Taxa de conversão
        $visualizacoes = $this->firestore->database()
            ->collection('visualizacoes')
            ->where('timestamp', '>=', $dataInicio)
            ->where('timestamp', '<=', $dataFim)
            ->documents();
        
        $totalVisualizacoes = $visualizacoes->size();
        $taxaConversao = $totalVisualizacoes > 0 
            ? round(($totalDoacoes / $totalVisualizacoes) * 100, 2) 
            : 0;
        
        // Top unidades (se nacional)
        $topUnidades = null;
        if ($user->role === 'nacional') {
            $topUnidades = $doacoes->groupBy('origem.unidade_id')
                ->map(function($d) {
                    return [
                        'nome' => $d->first()['origem']['unidade_nome'] ?? 'Não identificada',
                        'total' => $d->count(),
                        'valor' => $d->sum('valor')
                    ];
                })
                ->sortByDesc('total')
                ->take(10);
        }
        
        return view('analytics.index', compact(
            'porCanal',
            'rankingAdmins',
            'taxaConversao',
            'topUnidades',
            'totalDoacoes',
            'totalVisualizacoes'
        ));
    }
    
    /**
     * Exporta relatório de analytics
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function exportar(Request $request)
    {
        // TODO: Implementar exportação CSV/Excel
    }
}
```

### Camada de Views

#### campanha/show.blade.php (Atualizado)
```blade
@extends('layouts.app')

@section('content')
<div class="campanha-publica">
    {{-- 🆕 Banner de Origem --}}
    @if(isset($origem))
    <div class="origem-banner bg-blue-50 border-l-4 border-blue-500 p-6 mb-8 rounded-lg shadow-sm">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                    ✅ Você está doando para:
                </h3>
                <p class="text-gray-700 text-base">
                    <strong>{{ $origem['unidade_nome'] }}</strong>
                </p>
                @if($origem['canal'] !== 'direto')
                <p class="text-sm text-gray-600 mt-1">
                    Link compartilhado via 
                    @if($origem['canal'] === 'whatsapp')
                        <span class="font-semibold">WhatsApp</span>
                    @elseif($origem['canal'] === 'qrcode')
                        <span class="font-semibold">QR Code</span>
                    @elseif($origem['canal'] === 'email')
                        <span class="font-semibold">E-mail</span>
                    @endif
                    
                    @if(isset($origem['compartilhado_por_nome']))
                        por <strong>{{ $origem['compartilhado_por_nome'] }}</strong>
                    @endif
                </p>
                @endif
                
                {{-- Opção de mudar unidade --}}
                <button 
                    onclick="mostrarSeletorUnidade()"
                    class="text-blue-600 hover:text-blue-800 text-sm underline mt-2"
                >
                    Essa não é minha unidade? Clique para escolher
                </button>
            </div>
        </div>
    </div>
    @endif
    
    {{-- Conteúdo existente da campanha --}}
    <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-12 px-6 rounded-xl shadow-lg mb-8">
        <h1 class="text-4xl font-bold mb-3">{{ $campanha->titulo }}</h1>
        <p class="text-xl text-blue-100 mb-6">{{ $campanha->descricao }}</p>
        
        @if($campanha->meta_financeira)
        <div class="progress-bar bg-blue-800 rounded-full h-4 overflow-hidden">
            <div class="progress-fill bg-green-400 h-full transition-all" 
                 style="width: {{ min(($campanha->stats['total_arrecadado'] / $campanha->meta_financeira) * 100, 100) }}%">
            </div>
        </div>
        <p class="text-blue-100 mt-2">
            R$ {{ number_format($campanha->stats['total_arrecadado'], 2, ',', '.') }} 
            de R$ {{ number_format($campanha->meta_financeira, 2, ',', '.') }}
        </p>
        @endif
    </header>
    
    {{-- Lista de itens e formulário de doação (existente) --}}
    {{-- ... resto do conteúdo ... --}}
</div>

{{-- 🆕 Modal Seletor de Unidade --}}
<div id="modal-unidade" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Selecione sua unidade
            </h2>
            <p class="text-gray-600 mb-6">
                Isso nos ajuda a direcionar sua doação corretamente
            </p>
            
            <div class="grid md:grid-cols-2 gap-4 max-h-96 overflow-y-auto">
                @foreach($campanha->organizacao->unidades as $unidade)
                <button 
                    onclick="selecionarUnidade('{{ $unidade->id }}')"
                    class="text-left p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all"
                >
                    <div class="flex items-start gap-3">
                        <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $unidade->nome }}</h3>
                            <p class="text-sm text-gray-600">{{ $unidade->cidade }} - {{ $unidade->estado }}</p>
                        </div>
                    </div>
                </button>
                @endforeach
            </div>
            
            <button 
                onclick="fecharModalUnidade()"
                class="mt-6 w-full py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg transition-colors"
            >
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
function mostrarSeletorUnidade() {
    document.getElementById('modal-unidade').classList.remove('hidden');
}

function fecharModalUnidade() {
    document.getElementById('modal-unidade').classList.add('hidden');
}

function selecionarUnidade(unidadeId) {
    // Atualiza origem via AJAX
    fetch('/api/atualizar-origem', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ 
            unidade_id: unidadeId,
            campanha_id: '{{ $campanha->id }}'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recarrega página para mostrar nova origem
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Erro ao atualizar origem:', error);
        alert('Erro ao selecionar unidade. Tente novamente.');
    });
}
</script>
@endsection
```

#### admin/compartilhar.blade.php (Nova)
```blade
@extends('layouts.admin')

@section('content')
<div class="compartilhar-campanha max-w-4xl mx-auto p-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">
        Compartilhar Campanha
    </h1>
    
    <div class="bg-white rounded-xl shadow-lg p-8 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">
            {{ $campanha->titulo }}
        </h2>
        <p class="text-gray-600 mb-6">{{ $campanha->descricao }}</p>
        
        {{-- WhatsApp --}}
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                Compartilhar no WhatsApp
            </h3>
            
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-3">
                <pre id="mensagem-whatsapp" class="text-sm text-gray-700 whitespace-pre-wrap">{{ $mensagemWhatsApp }}</pre>
            </div>
            
            <button 
                onclick="copiarMensagemWhatsApp()"
                class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copiar Mensagem
            </button>
        </div>
        
        {{-- QR Code --}}
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                </svg>
                QR Code para Impressão
            </h3>
            
            <div class="flex justify-center mb-4">
                <img src="{{ route('compartilhamento.qrcode', $campanha) }}" 
                     alt="QR Code da Campanha"
                     class="w-64 h-64 border-4 border-gray-200 rounded-lg">
            </div>
            
            <button 
                onclick="baixarQRCode()"
                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Baixar QR Code (300 DPI)
            </button>
        </div>
        
        {{-- Link Direto --}}
        <div>
            <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
                Link Direto
            </h3>
            
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-3">
                <code id="link-direto" class="text-sm text-gray-700 break-all">{{ $linkDireto }}</code>
            </div>
            
            <button 
                onclick="copiarLinkDireto()"
                class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-semibold rounded-lg transition-colors flex items-center justify-center gap-2"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copiar Link
            </button>
        </div>
    </div>
    
    {{-- Estatísticas de Compartilhamento --}}
    @if($stats)
    <div class="bg-white rounded-xl shadow-lg p-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">
            📊 Suas Estatísticas de Compartilhamento
        </h2>
        
        <div class="grid md:grid-cols-3 gap-6">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-3xl font-bold text-blue-600 mb-2">
                    {{ $stats['visualizacoes'] }}
                </div>
                <div class="text-sm text-gray-600">Visualizações geradas</div>
            </div>
            
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-3xl font-bold text-green-600 mb-2">
                    {{ $stats['doacoes'] }}
                </div>
                <div class="text-sm text-gray-600">Doações convertidas</div>
            </div>
            
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-3xl font-bold text-purple-600 mb-2">
                    {{ $stats['taxa_conversao'] }}%
                </div>
                <div class="text-sm text-gray-600">Taxa de conversão</div>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
function copiarMensagemWhatsApp() {
    const mensagem = document.getElementById('mensagem-whatsapp').textContent;
    navigator.clipboard.writeText(mensagem).then(() => {
        alert('✅ Mensagem copiada! Cole no WhatsApp.');
    });
}

function copiarLinkDireto() {
    const link = document.getElementById('link-direto').textContent;
    navigator.clipboard.writeText(link).then(() => {
        alert('✅ Link copiado!');
    });
}

function baixarQRCode() {
    window.location.href = '{{ route("compartilhamento.qrcode", $campanha) }}?download=1';
}
</script>
@endsection
```

---

## 🛣️ Roadmap de Implementação

### Fase 1: Core (3-4 horas) - **PRIORIDADE CRÍTICA**

#### Sprint 1.1 - Services e Captura (2h)
- [ ] Criar `URLService` com métodos de geração de links
- [ ] Criar `OrigemService` com captura e armazenamento
- [ ] Criar `VisualizacaoService` para analytics
- [ ] Testes unitários dos services

#### Sprint 1.2 - Controllers e Rotas (1-2h)
- [ ] Atualizar `CampanhaController::show` para capturar origem
- [ ] Atualizar `DoacaoController::store` para vincular origem
- [ ] Criar `CompartilhamentoController` com métodos WhatsApp/QR/Email
- [ ] Criar `AnalyticsController` para dashboard
- [ ] Adicionar rotas no `web.php` e `api.php`

**Entregáveis Fase 1:**
✅ Links com rastreamento UTM funcionando
✅ Origem capturada e salva na sessão
✅ Doações vinculadas à origem automaticamente

---

### Fase 2: UI e UX (2-3 horas)

#### Sprint 2.1 - Interface Doador (1.5h)
- [ ] Banner de origem na página da campanha
- [ ] Modal seletor de unidade
- [ ] Feedback visual de origem capturada
- [ ] Testes de usabilidade

#### Sprint 2.2 - Interface Admin (1.5h)
- [ ] Página de compartilhamento com WhatsApp/QR/Link
- [ ] Botões de copiar/baixar
- [ ] Estatísticas pessoais de compartilhamento
- [ ] Testes de compartilhamento

**Entregáveis Fase 2:**
✅ Doador vê claramente onde está doando
✅ Admin compartilha facilmente com rastreamento
✅ Feedback visual em todas as interações

---

### Fase 3: Analytics e Dashboard (2-3 horas)

#### Sprint 3.1 - Dashboard Básico (1.5h)
- [ ] View `analytics.index` com métricas principais
- [ ] Gráficos de doações por canal
- [ ] Ranking de admins por conversão
- [ ] Taxa de conversão geral

#### Sprint 3.2 - Dashboard Avançado (1.5h)
- [ ] Filtros por período/unidade
- [ ] Comparação entre unidades (se nacional)
- [ ] Exportação de relatórios
- [ ] Detalhamento por campanha

**Entregáveis Fase 3:**
✅ Dashboard completo de analytics
✅ Métricas acionáveis para decisão
✅ Relatórios exportáveis

---

### Fase 4: Refinamentos (1-2 horas) - **OPCIONAL**

#### Sprint 4.1 - Melhorias (1-2h)
- [ ] Geolocalização por IP (integração externa)
- [ ] Notificações de conversão para admin
- [ ] Metas de compartilhamento
- [ ] Gamificação (ranking público)

**Entregáveis Fase 4:**
✅ Sistema completo e polido
✅ Funcionalidades avançadas opcionais

---

## 📋 Checklist de Validação

### Testes Funcionais

#### ✅ Captura de Origem
- [ ] Link sem UTM usa origem padrão da campanha
- [ ] Link com UTM captura origem corretamente
- [ ] Origem persiste durante toda a sessão
- [ ] Múltiplas abas/janelas não conflitam

#### ✅ Vinculação na Doação
- [ ] Doação salva com origem da sessão
- [ ] Origem vazia usa padrão da campanha
- [ ] Campos de origem preenchidos corretamente
- [ ] Visualização marcada como convertida

#### ✅ Compartilhamento
- [ ] Link WhatsApp gerado corretamente
- [ ] QR Code contém URL com rastreamento
- [ ] Link email funciona
- [ ] Copiar para clipboard funciona

#### ✅ Analytics
- [ ] Dashboard carrega métricas corretas
- [ ] Filtros funcionam corretamente
- [ ] Ranking de admins preciso
- [ ] Taxa de conversão calculada certa

### Testes de Segurança

- [ ] Parâmetros UTM validados (SQL injection)
- [ ] Sessão protegida contra CSRF
- [ ] IP anonimizado (LGPD)
- [ ] User-agent sanitizado

### Testes de Performance

- [ ] Captura de origem < 50ms
- [ ] Dashboard carrega < 2s
- [ ] Queries Firestore otimizadas
- [ ] Índices criados corretamente

---

## 📊 Métricas de Sucesso

### KPIs Principais

| Métrica | Objetivo | Como Medir |
|---------|----------|------------|
| **Taxa de Captura** | > 95% | (Doações com origem / Total doações) |
| **Taxa de Conversão** | > 30% | (Doações / Visualizações) |
| **Tempo para Doar** | < 3 min | Tempo entre acesso e doação |
| **Satisfação Admin** | > 4.5/5 | Survey pós-implementação |

### Métricas Secundárias

- **Canal mais efetivo**: WhatsApp esperado > 60%
- **Admin top performer**: Identificar e reconhecer
- **Unidade mais ativa**: Benchmark para outras
- **Horário de pico**: Otimizar comunicação

---

## 💰 Estimativa de Custos

### Custo de Implementação

| Fase | Horas | Valor/hora | Total |
|------|-------|------------|-------|
| Fase 1 (Core) | 4h | - | - |
| Fase 2 (UI) | 3h | - | - |
| Fase 3 (Analytics) | 3h | - | - |
| Fase 4 (Opcional) | 2h | - | - |
| **TOTAL** | **12h** | - | - |

### Custo Operacional

| Item | Custo Mensal | Observação |
|------|--------------|------------|
| Firebase (Firestore reads/writes) | R$ 0 | Free tier suficiente |
| Armazenamento visualizações | R$ 0 | < 100 MB/mês |
| Geolocalização IP (opcional) | R$ 0 | APIs gratuitas (10k/mês) |
| **TOTAL** | **R$ 0/mês** | Zero custo adicional |

---

## 🔒 Compliance e LGPD

### Dados Coletados

#### Necessários (Base Legal: Legítimo Interesse)
- ✅ Unidade de origem
- ✅ Canal de compartilhamento
- ✅ Admin que compartilhou
- ✅ Data/hora de acesso

#### Opcionais (Base Legal: Consentimento)
- ⚠️ Endereço IP (anonimizado)
- ⚠️ User-agent (sanitizado)
- ⚠️ Geolocalização (cidade/estado)

### Retenção de Dados

| Tipo de Dado | Período de Retenção | Justificativa |
|--------------|---------------------|---------------|
| Origem da doação | Permanente | Auditoria fiscal |
| Visualizações | 12 meses | Analytics |
| IP anonimizado | 30 dias | Segurança |
| User-agent | 30 dias | Debug |

### Anonimização de IP

```php
// app/Services/OrigemService.php
private function anonimizarIP(string $ip): string
{
    // IPv4: Remove último octeto
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return preg_replace('/\.\d+$/', '.0', $ip);
    }
    
    // IPv6: Remove últimos 80 bits
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 4)) . '::';
    }
    
    return 'unknown';
}
```

---

## 🎨 Exemplos de UI

### Banner de Origem (Doador)

```
┌────────────────────────────────────────────────────────────┐
│ ✅ Você está doando para:                                  │
│                                                            │
│ 📍 Unidade São Paulo - Centro                              │
│                                                            │
│ Link compartilhado via WhatsApp por Maria Silva           │
│                                                            │
│ [Essa não é minha unidade? Clique para escolher]          │
└────────────────────────────────────────────────────────────┘
```

### Dashboard Analytics (Admin)

```
┌────────────────────────────────────────────────────────────┐
│ 📊 Analytics de Origem - Últimos 30 dias                   │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Doações por Canal:                                        │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ WhatsApp: 65% (234)      │
│  ━━━━━━━━━━━━━━ QR Code: 20% (72)                         │
│  ━━━━━━━ Email: 10% (36)                                   │
│  ━━━ Direto: 5% (18)                                       │
│                                                            │
│  Taxa de Conversão: 37% (360 doações / 973 visualizações) │
│                                                            │
│  🏆 Top Admins:                                            │
│  1. Maria Silva     89 doações   R$ 8.450                  │
│  2. João Costa      67 doações   R$ 6.230                  │
│  3. Ana Santos      54 doações   R$ 5.120                  │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Página de Compartilhamento (Admin)

```
┌────────────────────────────────────────────────────────────┐
│ 📤 Compartilhar: Campanha Natal 2025                       │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  📱 WhatsApp                                               │
│  ┌────────────────────────────────────────────────────┐   │
│  │ 🎄 Campanha Natal 2025                             │   │
│  │                                                    │   │
│  │ 📍 Unidade São Paulo - Centro                      │   │
│  │                                                    │   │
│  │ Doe agora: listafacil.com/c/abc123?origem=sp...   │   │
│  └────────────────────────────────────────────────────┘   │
│  [Copiar Mensagem]                                         │
│                                                            │
│  🔲 QR Code                                                │
│  ┌────────────┐                                            │
│  │  ▄▄▄▄▄▄▄▄  │                                            │
│  │  █ ▄▄▄ █  │  [Baixar QR Code (300 DPI)]                │
│  │  █ ███ █  │                                            │
│  │  █▄▄▄▄▄█  │                                            │
│  └────────────┘                                            │
│                                                            │
│  🔗 Link Direto                                            │
│  listafacil.com/c/abc123?origem=sp&canal=whatsapp         │
│  [Copiar Link]                                             │
│                                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━   │
│                                                            │
│  📊 Suas Estatísticas:                                     │
│  32 visualizações | 12 doações | 37.5% conversão          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

---

## 🔧 Troubleshooting

### Problemas Comuns

#### 1. Origem não capturada
**Sintoma**: Doação sem campo `origem` preenchido

**Causas Possíveis**:
- Sessão expirada
- Cookie bloqueado
- Navegação em modo anônimo

**Solução**:
```php
// Fallback para origem padrão da campanha
$origem = session('origem_acesso', [
    'unidade_id' => $campanha->unidade_id,
    'canal' => 'direto'
]);
```

#### 2. Taxa de conversão incorreta
**Sintoma**: Percentual não bate com realidade

**Causas Possíveis**:
- Visualizações não registradas
- Múltiplas visualizações por usuário
- Bots/crawlers

**Solução**:
```php
// Filtrar visualizações duplicadas (mesmo IP em < 1 min)
$visualizacoes = Visualizacao::where('campanha_id', $campanhaId)
    ->where('created_at', '>', now()->subMinutes(1))
    ->distinct('ip')
    ->count();
```

#### 3. Links muito longos
**Sintoma**: URL com muitos parâmetros

**Causas Possíveis**:
- Múltiplos parâmetros UTM
- IDs longos

**Solução**:
```php
// Usar short IDs ou hash
$params = [
    'o' => hash('crc32', $unidade->id), // ao invés de 'origem'
    'c' => substr($canal, 0, 1), // 'w' para whatsapp
    'a' => hash('crc32', $admin->id)
];
```

---

## 📚 Referências Técnicas

### Documentação Externa
- [Google Analytics UTM Parameters](https://support.google.com/analytics/answer/1033863)
- [Firebase Firestore Best Practices](https://firebase.google.com/docs/firestore/best-practices)
- [Laravel Session Documentation](https://laravel.com/docs/10.x/session)
- [LGPD - Lei 13.709/2018](http://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)

### Bibliotecas Utilizadas
- **SimpleSoftwareIO/simple-qrcode**: Geração de QR Codes
- **Kreait/firebase-php**: SDK Firebase para PHP
- **Laravel/framework**: Framework base

---

## ✅ Checklist de Deploy

### Pré-Deploy

- [ ] Todos os testes passando
- [ ] Migrations criadas e testadas
- [ ] Firestore indexes criados
- [ ] Variáveis de ambiente configuradas
- [ ] Documentação atualizada

### Deploy

- [ ] Backup do banco de dados
- [ ] Deploy do código (Git pull / CI/CD)
- [ ] Executar migrations: `php artisan migrate`
- [ ] Limpar cache: `php artisan cache:clear`
- [ ] Otimizar: `php artisan optimize`
- [ ] Verificar logs: `tail -f storage/logs/laravel.log`

### Pós-Deploy

- [ ] Testar captura de origem em produção
- [ ] Testar compartilhamento WhatsApp
- [ ] Testar geração de QR Code
- [ ] Verificar analytics no dashboard
- [ ] Monitorar erros nos primeiros 24h

### Rollback (se necessário)

```bash
# Reverter código
git revert HEAD

# Reverter migrations
php artisan migrate:rollback --step=1

# Limpar cache
php artisan cache:clear
php artisan config:clear
```

---

## 🎓 Treinamento de Usuários

### Para Admins

#### Passo 1: Compartilhar Campanha
1. Acesse o dashboard
2. Selecione a campanha
3. Clique em "Compartilhar"
4. Escolha o canal (WhatsApp, QR Code, Email)
5. Copie e compartilhe

#### Passo 2: Acompanhar Métricas
1. Acesse "Analytics"
2. Veja suas estatísticas pessoais
3. Compare com outras unidades
4. Identifique melhor canal

#### Passo 3: Otimizar Divulgação
1. Foque no canal com maior conversão
2. Compartilhe em horários de pico
3. Personalize mensagem
4. Acompanhe resultados

### Para Doadores

#### Interface Transparente
- Banner claro de onde está doando
- Opção de mudar unidade se necessário
- Sem fricção adicional
- Processo normal de doação

---

## 🚀 Próximos Passos Recomendados

### Curto Prazo (1-2 meses)
1. ✅ Implementar Fase 1-3 (core + UI + analytics)
2. ✅ Treinar admins das unidades
3. ✅ Coletar feedback inicial
4. ✅ Ajustar conforme necessário

### Médio Prazo (3-6 meses)
1. 📊 Analisar dados coletados
2. 🎯 Identificar padrões de sucesso
3. 🏆 Criar ranking público (gamificação)
4. 📱 Integrar com WhatsApp Business API (notificações)

### Longo Prazo (6-12 meses)
1. 🤖 Machine Learning para prever conversão
2. 🎨 A/B testing de mensagens
3. 🌐 Integração com Google Analytics
4. 📈 Dashboard executivo nacional

---

## 📞 Suporte e Contato

### Documentação Técnica
- **Repositório**: github.com/listafacil/rastreamento-origem
- **Wiki**: wiki.listafacil.com/rastreamento
- **Changelog**: CHANGELOG.md

### Equipe de Desenvolvimento
- **Tech Lead**: [Nome]
- **Backend**: [Nome]
- **Frontend**: [Nome]
- **QA**: [Nome]

### Canais de Suporte
- **Issues GitHub**: Para bugs técnicos
- **Slack #dev-listafacil**: Para discussões
- **Email**: dev@listafacil.com.br

---

## 📄 Conclusão

O **Sistema de Rastreamento de Origem** resolve um problema crítico do Listafacil: a impossibilidade de rastrear de onde vêm as doações quando links circulam entre múltiplas unidades.

### Diferenciais da Solução

✅ **Zero Fricção**: Doadores não precisam fazer nada diferente
✅ **Transparência Total**: Banner claro mostrando onde estão doando
✅ **Analytics Completo**: Dashboard detalhado para admins
✅ **Custo Zero**: Usa infraestrutura existente (Firebase)
✅ **Implementação Rápida**: 8-12 horas total
✅ **Escalável**: Funciona para 1 ou 1.000 unidades
✅ **LGPD Compliant**: Dados anonimizados e com retenção definida

### ROI Esperado

- **Aumento de 20-30%** nas doações (melhor direcionamento)
- **Redução de 50%** em dúvidas sobre "onde doar"
- **Identificação** de admins mais efetivos
- **Dados** para tomada de decisão estratégica

### Métricas de Sucesso (6 meses)

| Métrica | Objetivo | Status |
|---------|----------|--------|
| Taxa de captura de origem | > 95% | 🎯 |
| Satisfação dos admins | > 4.5/5 | 🎯 |
| Doações rastreadas | > 90% | 🎯 |
| Taxa de conversão | > 30% | 🎯 |

---

**Documento preparado por**: Equipe de Engenharia Listafacil  
**Versão**: 1.0  
**Data**: Janeiro 2025  
**Status**: ✅ Pronto para Implementação  
**Estimativa**: 8-12 horas de desenvolvimento  
**Custo**: R$ 0/mês (zero custo operacional)

---

*Este documento é parte integrante da documentação técnica do Listafacil v3.0 e deve ser mantido atualizado conforme evoluções do sistema.*