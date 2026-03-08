# CustomLoader — 설정 가이드

> `plugins/CustomLoader/config.yml` 파일을 수정하여 커스텀 콘텐츠를 추가합니다.
> 변경사항 적용은 서버 재시작이 필요합니다.

---

## 목차

- [아이템 (items)](#-아이템-items)
- [블록 (blocks)](#-블록-blocks)
- [엔티티 (entities)](#-엔티티-entities)
- [전체 예시](#-전체-예시)

---

## 🗡️ 아이템 (items)

### 필수 속성

| 속성 | 타입 | 설명 |
|---|---|---|
| `namespace` | string | 고유 식별자. `"팩이름:아이템id"` 형식 (예: `"mypack:my_sword"`) |
| `texture` | string | `textures/items/` 기준 텍스처 파일명 (확장자 제외) |

### 공통 선택 속성

| 속성 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `max_stack_size` | int | `64` | 최대 스택 크기 |
| `allow_off_hand` | bool | `false` | 부 손 장착 허용 |
| `hand_equipped` | bool | `false` | 손에 들었을 때 칼처럼 표시 |
| `can_destroy_in_creative` | bool | `false` | 크리에이티브에서 블록 파괴 가능 |
| `add_creative_inventory` | bool | `false` | 크리에이티브 인벤토리에 추가 |
| `creative_category` | int | `1` | 크리에이티브 카테고리 (1=건축, 2=자연, 3=장비, 4=아이템) |
| `attack_points` | int | `0` | 추가 공격력 |
| `foil` | bool | `false` | 인첸트 반짝임 효과 |
| `cooldown` | int | `0` | 사용 쿨다운 (틱 단위) |

---

### 내구도 아이템 (Durable)

`durable: true` 사용 시 `max_durability`가 **필수**입니다.

```yaml
items:
  magic_wand:
    namespace: "mypack:magic_wand"
    texture: "magic_wand"
    durable: true
    max_durability: 300
    hand_equipped: true
    attack_points: 5
    add_creative_inventory: true
```

---

### 음식 (Food)

`food: true` 사용 시 `nutrition`, `saturation`, `can_always_eat`이 **필수**입니다.

```yaml
items:
  golden_apple_plus:
    namespace: "mypack:golden_apple_plus"
    texture: "golden_apple_plus"
    food: true
    nutrition: 10
    saturation: 12.0
    can_always_eat: true
    add_creative_inventory: true
```

---

### 방어구 (Armor)

`armor: true` 사용 시 `defence_points`, `armor_slot`, `armor_class`가 **필수**입니다.

```yaml
items:
  ruby_helmet:
    namespace: "mypack:ruby_helmet"
    texture: "ruby_helmet"
    armor: true
    defence_points: 3
    armor_slot: helmet       # helmet / chest / leggings / boots
    armor_class: diamond     # 아래 표 참고
    durable: true
    max_durability: 363
    add_creative_inventory: true
```

**`armor_class` 허용값:** `gold` · `leather` · `chain` · `iron` · `diamond` · `netherite` · `turtle` · `elytra` · `none`

---

### 도구 (Tool)

`tool: true` 사용 시 `tool_type`과 `tool_tier`가 **필수**입니다.

```yaml
items:
  ruby_pickaxe:
    namespace: "mypack:ruby_pickaxe"
    texture: "ruby_pickaxe"
    tool: true
    tool_type: 4
    tool_tier: 5
    attack_points: 3
    durable: true
    max_durability: 1561
    add_creative_inventory: true
```

**`tool_type` 값:**

| 값 | 도구 | | 값 | 도구 |
|---|---|---|---|---|
| `0` | 없음 | | `8` | 도끼 |
| `1` | 검 | | `16` | 가위 |
| `2` | 삽 | | `32` | 괭이 |
| `4` | 곡괭이 | | | |

**`tool_tier` 값:** `1`=나무 · `2`=금 · `3`=돌 · `4`=철 · `5`=다이아 · `6`=네더라이트

---

### 채굴 속성 (Dig)

특정 블록에만 빠른 채굴을 적용합니다.

```yaml
items:
  super_pickaxe:
    namespace: "mypack:super_pickaxe"
    texture: "super_pickaxe"
    tool: true
    tool_type: 4
    tool_tier: 5
    dig:
      speed: 10
      block_tags:
        - "minecraft:stone"
        - "minecraft:iron_ore"
```

---

## 🧱 블록 (blocks)

### 필수 속성

| 속성 | 타입 | 설명 |
|---|---|---|
| `namespace` | string | 고유 식별자. `"팩이름:블록id"` 형식 |
| `texture` | string | `textures/blocks/` 기준 텍스처 파일명 |

### 선택 속성

| 속성 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `hardness` | float | `1.5` | 파괴 경도 |
| `blast_resistance` | float | `hardness와 동일` | 폭발 저항 |
| `tool_type` | string | `"none"` | 올바른 채굴 도구 |
| `tool_tier` | int | `0` | 최소 채굴 등급 |
| `light_emission` | int | `0` | 발광 레벨 (0~15) |
| `creative_category` | int | `4` | 크리에이티브 카테고리 |
| `model` | string | — | 커스텀 geometry ID |
| `drops` | array | `[]` | 파괴 시 드랍 아이템 |

**`tool_type` 허용값:** `pickaxe` · `axe` · `shovel` · `hoe` · `sword` · `shears` · `none`

### 예시

```yaml
blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    hardness: 3.0
    blast_resistance: 3.0
    tool_type: "pickaxe"
    tool_tier: 2
    drops:
      - id: "mypack:ruby_gem"   # 드랍 아이템 ID
        count: 1                # 드랍 수량
        chance: 1.0             # 드랍 확률 (0.0~1.0)

  crystal_block:
    namespace: "mypack:crystal_block"
    texture: "crystal_block"
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1
    light_emission: 10          # 발광 레벨
```

---

## 🐾 엔티티 (entities)

### 필수 속성

| 속성 | 타입 | 설명 |
|---|---|---|
| `namespace` | string | 고유 식별자. `"팩이름:엔티티id"` 형식 |
| `texture` | string | `textures/entity/` 기준 텍스처 파일명 |

### 선택 속성

| 속성 | 타입 | 기본값 | 설명 |
|---|---|---|---|
| `width` | float | `0.6` | 히트박스 너비 |
| `height` | float | `1.8` | 히트박스 높이 |
| `max_health` | float | `20` | 최대 체력 |
| `attack_damage` | float | `2.0` | 공격력 |
| `movement_speed` | float | `0.25` | 이동 속도 |
| `follow_range` | float | `16.0` | 추적 거리 |
| `model` | string | — | 커스텀 geometry ID |
| `goals` | array | `[]` | AI 행동 목록 |
| `drops` | array | `[]` | 사망 드랍 아이템 |

### AI Goals 설정

`priority`는 낮을수록 우선도가 높습니다.

```yaml
entities:
  my_mob:
    namespace: "mypack:my_mob"
    texture: "my_mob"
    max_health: 30
    attack_damage: 4.0
    movement_speed: 0.3
    goals:
      - type: float              # 물에서 떠있기
        priority: 0

      - type: hurt_by_target     # 피격 시 반격
        priority: 1

      - type: melee_attack       # 근접 공격
        priority: 2
        speed_modifier: 1.2      # 공격 시 이동속도 배율

      - type: nearest_attackable # 타겟 탐색 및 추적
        priority: 3
        target: player           # "player" (기본값)
        distance: 24.0           # 감지 거리

      - type: random_stroll      # 무작위 배회
        priority: 7
        speed_modifier: 1.0

      - type: look_at_entity     # 가까운 엔티티 바라보기
        priority: 8
        look_distance: 8.0
```

**Goal 타입 전체 목록:**

| 타입 | 설명 | 옵션 |
|---|---|---|
| `float` | 물 위에 뜨기 | — |
| `random_stroll` | 무작위 배회 | `speed_modifier` (기본: `1.0`) |
| `melee_attack` | 근접 공격 | `speed_modifier` (기본: `1.0`) |
| `look_at_entity` | 엔티티 바라보기 | `look_distance` (기본: `8.0`) |
| `hurt_by_target` | 피격 반격 | — |
| `nearest_attackable` | 타겟 추적 | `distance` (기본: `16.0`), `target` (기본: `"player"`) |

---

## 📋 전체 예시

```yaml
# plugins/CustomLoader/config.yml

items:
  ruby_sword:
    namespace: "mypack:ruby_sword"
    texture: "ruby_sword"
    attack_points: 10
    hand_equipped: true
    durable: true
    max_durability: 1000
    add_creative_inventory: true

  magic_cake:
    namespace: "mypack:magic_cake"
    texture: "magic_cake"
    food: true
    nutrition: 8
    saturation: 10.0
    can_always_eat: true
    add_creative_inventory: true

  ruby_chestplate:
    namespace: "mypack:ruby_chestplate"
    texture: "ruby_chestplate"
    armor: true
    defence_points: 8
    armor_slot: chest
    armor_class: diamond
    durable: true
    max_durability: 528
    add_creative_inventory: true

blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    hardness: 3.0
    blast_resistance: 3.0
    tool_type: "pickaxe"
    tool_tier: 2
    drops:
      - id: "mypack:ruby_gem"
        count: 1
        chance: 1.0

  crystal_block:
    namespace: "mypack:crystal_block"
    texture: "crystal_block"
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1
    light_emission: 10

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
      - { type: look_at_entity,     priority: 8, look_distance: 10.0 }
    drops:
      - id: "mypack:ruby_gem"
        count: 3
        chance: 1.0
```
