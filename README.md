<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=6,11,20&height=200&section=header&text=CustomLoader&fontSize=70&fontColor=fff&animation=twinkling&fontAlignY=35&desc=PocketMine-MP%205%20Custom%20Content%20Plugin&descAlignY=58&descAlign=50" width="100%"/>

<br/>

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PocketMine-MP](https://img.shields.io/badge/PocketMine--MP-5.x-orange)](https://github.com/pmmp/PocketMine-MP)
[![License](https://img.shields.io/badge/License-LGPL--3.0-blue)](./LICENSE)
[![API](https://img.shields.io/badge/API-5.0.0-brightgreen)](https://github.com/pmmp/PocketMine-MP)
[![CI](https://github.com/boredape874/CustomItemLoaderPM-5/actions/workflows/ci.yml/badge.svg)](https://github.com/boredape874/CustomItemLoaderPM-5/actions/workflows/ci.yml)

<br/>

**YAML 설정 파일 하나로 커스텀 아이템 · 블록 · 엔티티를 Bedrock 애드온처럼 등록하세요.**

[📥 설치](#-설치) · [⚡ 빠른 시작](#-빠른-시작) · [📋 설정 가이드](./CONFIGURATION.md) · [🔧 커맨드](#-커맨드)

</div>

---

## ✨ 기능

| 기능 | 설명 |
|---|---|
| 🗡️ **커스텀 아이템** | 내구도 · 음식 · 방어구 · 도구 · 쿨다운 · 연료 등 모든 타입 지원 |
| 🧱 **커스텀 블록** | cube / slab / stair / fence / leaves 5가지 형태 지원 |
| 🐾 **커스텀 엔티티** | Goal 기반 AI (배회 · 추격 · 공격 · 반격 · 스폰 규칙) |
| ⚡ **이벤트 훅** | 우클릭 · 공격 · 섭취 · 파괴 · 설치 · 상호작용 시 액션 실행 |
| 🎲 **루트 테이블** | 가중치 기반 드랍 테이블로 복잡한 드랍 구성 |
| 📜 **커스텀 레시피** | shaped / shapeless / 화로 / 용광로 / 훈연기 / 석재 절단기 |
| 🔊 **사운드 / 파티클** | 커스텀 사운드·파티클 등록 및 훅에서 재생 |
| 📦 **리소스팩 자동 빌드** | `/cl build` 한 방으로 `.mcpack` 파일 생성 |
| ⚙️ **YAML 설정** | 코드 없이 `config.yml` 수정만으로 콘텐츠 추가 |

---

## 📥 설치

1. [Releases](../../releases/latest)에서 최신 `.phar` 파일 다운로드
2. 서버의 `plugins/` 폴더에 넣기
3. 서버 재시작
4. `plugins/CustomLoader/config.yml` 편집 후 재시작

---

## ⚡ 빠른 시작

### 1. config.yml 작성

```yaml
# plugins/CustomLoader/config.yml

items:
  fire_sword:
    namespace: "mypack:fire_sword"
    texture: "fire_sword"
    attack_points: 8
    hand_equipped: true
    durable: true
    max_durability: 500
    add_creative_inventory: true
    on_attack:
      - action: set_on_fire
        seconds: 3
        target: target
      - action: give_xp
        amount: 1

blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    hardness: 3.0
    tool_type: "pickaxe"
    tool_tier: 2
    xp_drop: { min: 3, max: 7 }
    drops:
      - id: "minecraft:diamond"
        count: 1
        chance: 1.0
    on_break:
      - action: play_sound
        sound: "dig.stone"
        volume: 1.0
        pitch: 0.8

entities:
  ruby_golem:
    namespace: "mypack:ruby_golem"
    texture: "ruby_golem"
    width: 1.4
    height: 2.9
    max_health: 100
    attack_damage: 15.0
    movement_speed: 0.25
    goals:
      - { type: float,              priority: 0 }
      - { type: hurt_by_target,     priority: 1 }
      - { type: melee_attack,       priority: 2, speed_modifier: 0.8 }
      - { type: nearest_attackable, priority: 3, distance: 20.0, target: player }
      - { type: random_stroll,      priority: 7, speed_modifier: 1.0 }
      - { type: look_at_entity,     priority: 8, look_distance: 8.0 }
```

### 2. 리소스팩 생성

```
/cl create mypack
```

생성된 폴더에 텍스처 PNG를 넣은 뒤:

```
/cl build mypack
```

생성된 `.mcpack` 파일을 `resource_packs/` 에 넣고 `pocketmine.yml` 에 등록하면 완료입니다.

---

## 📁 자동 생성 폴더 구조

```
plugins/CustomLoader/
├── config.yml
├── resource_packs/
│   └── mypack/
│       ├── manifest.json
│       ├── textures/
│       │   ├── items/          ← 아이템 텍스처 PNG
│       │   ├── blocks/         ← 블록 텍스처 PNG
│       │   └── entity/         ← 엔티티 텍스처 PNG
│       ├── models/entity/      ← 커스텀 모델 .geo.json (선택)
│       ├── entity/             ← 클라이언트 엔티티 정의 (자동 생성)
│       ├── sounds/             ← 커스텀 사운드 .ogg
│       ├── particles/          ← 커스텀 파티클 JSON (자동 생성)
│       └── texts/en_US.lang    ← 이름 현지화 (자동 생성)
└── behavior_packs/
    └── mypack/
        ├── manifest.json
        ├── blocks/             ← 블록 behavior (자동 생성)
        └── entities/           ← 엔티티 behavior (자동 생성)
```

---

## 🔧 커맨드

권한: `customloader.command` (기본: OP) · 별칭: `/cl`

| 커맨드 | 설명 |
|---|---|
| `/cl create <팩이름> [설명]` | 리소스팩 + 비헤이비어팩 폴더 생성 |
| `/cl build <팩이름>` | `.mcpack` 파일 빌드 |
| `/cl additem <팩> <이름> <namespace>` | 아이템 항목 수동 추가 |
| `/cl reload` | config.yml 리로드 |

---

## 🧱 블록 타입

| 타입 | 설명 |
|---|---|
| `cube` | 기본 정육면체 블록 (기본값) |
| `slab` | 반 블록 (아래/위/양면 배치 지원) |
| `stair` | 계단 (방향·뒤집기 지원) |
| `fence` | 울타리 (인접 울타리 자동 연결) |
| `leaves` | 낙엽 (`no_decay: true`로 소멸 방지) |

---

## ⚡ 이벤트 훅 & 액션

훅에서 사용할 수 있는 액션 목록:

| 액션 | 설명 |
|---|---|
| `give_effect` | 포션 효과 부여 |
| `set_health` | 체력 add / remove / set |
| `set_on_fire` | 불 붙이기 |
| `give_xp` | 경험치 추가/차감 |
| `give_item` | 아이템 지급 |
| `play_sound` | 사운드 재생 |
| `play_particle` | 파티클 재생 |
| `spawn_entity` | 엔티티 소환 |
| `run_command` | 서버 커맨드 실행 (`{player}` 치환 가능) |
| `damage` | 직접 피해 입히기 |

액션 상세 옵션 → **[CONFIGURATION.md — 이벤트 액션](./CONFIGURATION.md#-이벤트-액션-actions)**

---

## 🤖 엔티티 AI Goals

| 타입 | 설명 | 옵션 |
|---|---|---|
| `float` | 물 위에 뜨기 | — |
| `random_stroll` | 무작위 배회 | `speed_modifier` |
| `melee_attack` | 근접 공격 | `speed_modifier` |
| `look_at_entity` | 엔티티 바라보기 | `look_distance` |
| `hurt_by_target` | 피격 반격 | — |
| `nearest_attackable` | 가장 가까운 타겟 추적 | `distance`, `target` |

---

## 🛠️ 아이템 타입별 설정

<details>
<summary>🗡️ 도구 (Tool)</summary>

```yaml
items:
  my_pickaxe:
    namespace: "mypack:my_pickaxe"
    texture: "my_pickaxe"
    tool: true
    tool_type: 4     # 0=없음 1=검 2=삽 4=곡괭이 8=도끼 16=가위 32=괭이
    tool_tier: 5     # 1=나무 2=금 3=돌 4=철 5=다이아
    attack_points: 5
    add_creative_inventory: true
```

</details>

<details>
<summary>🍖 음식 (Food)</summary>

```yaml
items:
  my_food:
    namespace: "mypack:my_food"
    texture: "my_food"
    food: true
    nutrition: 6
    saturation: 8.0
    can_always_eat: false
    add_creative_inventory: true
    on_eat:
      - action: give_effect
        effect: regeneration
        duration: 100
        amplifier: 0
```

</details>

<details>
<summary>🛡️ 방어구 (Armor)</summary>

```yaml
items:
  my_helmet:
    namespace: "mypack:my_helmet"
    texture: "my_helmet"
    armor: true
    armor_slot: helmet     # helmet / chest / leggings / boots
    armor_class: diamond   # gold / leather / chain / iron / diamond / netherite
    defence_points: 3
    durable: true
    max_durability: 363
```

</details>

<details>
<summary>🔥 화로 연료 (Fuel)</summary>

```yaml
items:
  custom_coal:
    namespace: "mypack:custom_coal"
    texture: "custom_coal"
    fuel: 1600     # 바닐라 석탄과 동일 (틱 단위)
    add_creative_inventory: true
```

</details>

<details>
<summary>🧱 슬랩 / 계단 / 울타리 / 낙엽</summary>

```yaml
blocks:
  my_slab:
    namespace: "mypack:my_slab"
    texture: "my_slab"
    type: slab
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1

  my_stair:
    namespace: "mypack:my_stair"
    texture: "my_stair"
    type: stair
    hardness: 2.0

  my_fence:
    namespace: "mypack:my_fence"
    texture: "my_fence"
    type: fence
    hardness: 2.0
    tool_type: "axe"

  magic_leaves:
    namespace: "mypack:magic_leaves"
    texture: "magic_leaves"
    type: leaves
    no_decay: true
    light_emission: 5
```

</details>

---

## ❓ FAQ

**Q. 클라이언트 크래시가 발생해요**
> 텍스처 해상도를 확인하세요. Bedrock는 2의 제곱수 해상도(16×16, 32×32, 64×64)를 권장합니다.

**Q. 텍스처가 표시되지 않아요**
> PNG 파일이 올바른 폴더에 있는지 확인하고, `/cl build` 로 팩을 다시 빌드해보세요.

**Q. 엔티티가 움직이지 않아요**
> `goals` 목록에 `random_stroll`을 추가하고, `movement_speed`가 0이 아닌지 확인하세요.

**Q. 설정 변경이 바로 적용되지 않아요**
> 블록·아이템·엔티티 추가/변경은 서버 재시작이 필요합니다. `/cl reload`는 config 파일만 다시 읽습니다.

**Q. on_attack 훅이 안 작동해요**
> 아이템이 `CustomItemInterface`를 구현해야 합니다. CustomLoader로 등록된 아이템만 훅이 작동합니다.

**Q. 블록 드랍이 두 번 나와요**
> `drops`와 `loot_table`을 동시에 설정하면 `loot_table`만 사용됩니다. 하나만 쓰세요.

---

## 📋 상세 설정 가이드

모든 속성에 대한 상세 설명은 **[CONFIGURATION.md](./CONFIGURATION.md)** 를 참고하세요.

---

## 📄 라이선스

[GNU Lesser General Public License v3.0](./LICENSE)

<div align="center">

<br/>

<img src="https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=6,11,20&height=100&section=footer" width="100%"/>

</div>
