<div align="center">

<img src="https://capsule-render.vercel.app/api?type=waving&color=gradient&customColorList=6,11,20&height=200&section=header&text=CustomLoader&fontSize=70&fontColor=fff&animation=twinkling&fontAlignY=35&desc=PocketMine-MP%205%20Custom%20Content%20Plugin&descAlignY=58&descAlign=50" width="100%"/>

<br/>

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![PocketMine-MP](https://img.shields.io/badge/PocketMine--MP-5.x-orange)](https://github.com/pmmp/PocketMine-MP)
[![License](https://img.shields.io/badge/License-LGPL--3.0-blue)](./LICENSE)
[![API](https://img.shields.io/badge/API-5.0.0-brightgreen)](https://github.com/pmmp/PocketMine-MP)

<br/>

**YAML 설정 파일 하나로 커스텀 아이템 · 블록 · 엔티티를 Bedrock 애드온처럼 등록하세요.**

[📥 설치](#-설치) · [⚡ 빠른 시작](#-빠른-시작) · [📋 설정 가이드](./CONFIGURATION.md) · [🔧 커맨드](#-커맨드)

</div>

---

## ✨ 기능

| 기능 | 설명 |
|---|---|
| 🗡️ **커스텀 아이템** | 내구도 · 음식 · 방어구 · 도구 · 쿨다운 등 모든 타입 지원 |
| 🧱 **커스텀 블록** | 경도 · 채굴 도구 · 드랍 아이템 · 발광 설정 |
| 🐾 **커스텀 엔티티** | Goal 기반 AI (배회 · 추격 · 공격 · 반격) |
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
  my_sword:
    namespace: "mypack:my_sword"
    texture: "my_sword"
    attack_points: 8
    hand_equipped: true
    add_creative_inventory: true

blocks:
  my_ore:
    namespace: "mypack:my_ore"
    texture: "my_ore"
    hardness: 3.0
    tool_type: "pickaxe"
    tool_tier: 1
    drops:
      - id: "minecraft:diamond"
        count: 1
        chance: 1.0

entities:
  my_mob:
    namespace: "mypack:my_mob"
    texture: "my_mob"
    width: 0.6
    height: 1.8
    max_health: 20
    attack_damage: 3.0
    movement_speed: 0.25
    goals:
      - { type: float,              priority: 0 }
      - { type: hurt_by_target,     priority: 1 }
      - { type: melee_attack,       priority: 2, speed_modifier: 1.0 }
      - { type: nearest_attackable, priority: 3, distance: 16.0, target: player }
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
| `/cl reload` | config.yml 리로드 (전체 적용은 재시작 필요) |

---

## 🤖 엔티티 AI Goals

| 타입 | 설명 | 옵션 |
|---|---|---|
| `float` | 물 위에 뜨기 | — |
| `random_stroll` | 무작위 배회 | `speed_modifier` |
| `melee_attack` | 근접 공격 | `speed_modifier` |
| `look_at_entity` | 엔티티 바라보기 | `look_distance` |
| `hurt_by_target` | 공격받으면 반격 | — |
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
<summary>⚔️ 내구도 아이템 (Durable)</summary>

```yaml
items:
  my_blade:
    namespace: "mypack:my_blade"
    texture: "my_blade"
    durable: true
    max_durability: 500
    attack_points: 7
    hand_equipped: true
```

</details>

<details>
<summary>🧱 커스텀 블록 (Block)</summary>

```yaml
blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    hardness: 3.0
    blast_resistance: 3.0
    tool_type: "pickaxe"    # pickaxe / axe / shovel / hoe / sword / shears / none
    tool_tier: 2
    light_emission: 0
    drops:
      - id: "minecraft:diamond"
        count: 1
        chance: 0.5
      - id: "minecraft:cobblestone"
        count: 1
        chance: 1.0
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
