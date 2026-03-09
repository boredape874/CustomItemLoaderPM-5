# CustomLoader — 설정 완전 가이드

> `plugins/CustomLoader/config.yml` 파일을 수정하여 커스텀 콘텐츠를 추가합니다.
> **블록·아이템·엔티티 변경은 서버 재시작이 필요합니다.** `/cl reload`는 config만 다시 읽습니다.

---

## 목차

- [아이템 (items)](#️-아이템-items)
- [블록 (blocks)](#-블록-blocks)
- [엔티티 (entities)](#-엔티티-entities)
- [이벤트 훅 (Event Hooks)](#-이벤트-훅-event-hooks)
- [이벤트 액션 (Actions)](#-이벤트-액션-actions)
- [루트 테이블 (loot_tables)](#-루트-테이블-loot_tables)
- [레시피 (recipes)](#-레시피-recipes)
- [사운드 (sounds)](#-사운드-sounds)
- [파티클 (particles)](#-파티클-particles)

---

## 🗡️ 아이템 (items)

### 필수 속성

| 속성 | 타입 | 설명 |
|---|---|---|
| `namespace` | string | 고유 식별자. `"팩이름:아이템id"` 형식 |
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
| `cooldown` | int | `0` | 사용 쿨다운 (틱 단위, 20틱 = 1초) |
| `fuel` | int | `0` | 화로 연료 시간 (틱). 바닐라 통나무 = 300 |
| `on_use` | array | `[]` | 아이템 우클릭 시 실행할 액션 목록 |
| `on_attack` | array | `[]` | 엔티티 공격 시 실행할 액션 목록 |
| `on_eat` | array | `[]` | 음식 섭취 완료 시 실행할 액션 목록 (food만) |

---

### 내구도 아이템 (Durable)

`durable: true` 시 `max_durability` **필수**

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
    on_use:
      - action: play_sound
        sound: "random.orb"
        volume: 1.0
        pitch: 1.5
```

---

### 음식 (Food)

`food: true` 시 `nutrition`, `saturation`, `can_always_eat` **필수**

```yaml
items:
  magic_cake:
    namespace: "mypack:magic_cake"
    texture: "magic_cake"
    food: true
    nutrition: 8
    saturation: 10.0
    can_always_eat: true
    add_creative_inventory: true
    on_eat:
      - action: give_effect
        effect: regeneration
        duration: 100
        amplifier: 0
      - action: give_xp
        amount: 5
```

---

### 방어구 (Armor)

`armor: true` 시 `defence_points`, `armor_slot`, `armor_class` **필수**

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

`tool: true` 시 `tool_type`과 `tool_tier` **필수**

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

### 채굴 속성 (dig)

특정 블록 태그에 대해 빠른 채굴을 적용합니다.

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

### 연료 (fuel)

화로에 연료로 사용할 수 있습니다. 단위는 틱입니다.

```yaml
items:
  custom_log:
    namespace: "mypack:custom_log"
    texture: "custom_log"
    fuel: 300        # 바닐라 통나무와 동일
    add_creative_inventory: true
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
| `type` | string | `"cube"` | 블록 형태. `cube` / `slab` / `stair` / `fence` / `leaves` |
| `hardness` | float | `1.5` | 파괴 경도 |
| `blast_resistance` | float | `hardness` 와 동일 | 폭발 저항 |
| `tool_type` | string | `"none"` | 올바른 채굴 도구 |
| `tool_tier` | int | `0` | 최소 채굴 등급 |
| `light_emission` | int | `0` | 발광 레벨 (0~15) |
| `no_decay` | bool | `false` | `leaves` 타입 전용. `true`이면 자연소멸 없음 |
| `xp_drop` | int / map | `0` | 파괴 시 XP 드랍. 숫자 또는 `{min: 1, max: 3}` |
| `drops` | array | `[]` | 파괴 시 드랍 아이템 |
| `loot_table` | string | — | loot_tables 섹션의 테이블 이름 (drops 대신 사용) |
| `on_break` | array | `[]` | 파괴 시 실행할 액션 목록 |
| `on_place` | array | `[]` | 설치 시 실행할 액션 목록 |
| `on_interact` | array | `[]` | 우클릭 시 실행할 액션 목록 |

**`tool_type` 허용값:** `pickaxe` · `axe` · `shovel` · `hoe` · `sword` · `shears` · `none`

### 블록 타입 (type)

| 타입 | 설명 |
|---|---|
| `cube` | 기본 정육면체 블록 (기본값) |
| `slab` | 반 블록. 아래/위/양쪽에 놓을 수 있음 |
| `stair` | 계단 블록. 방향 및 뒤집기 지원 |
| `fence` | 울타리 블록. 인접 울타리에 자동 연결 |
| `leaves` | 낙엽 블록. `no_decay: true` 시 자연소멸 없음 |

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
    xp_drop:
      min: 3
      max: 7
    loot_table: "ruby_ore_drops"
    on_break:
      - action: play_sound
        sound: "dig.stone"
        volume: 1.0
        pitch: 0.8

  magic_altar:
    namespace: "mypack:magic_altar"
    texture: "magic_altar"
    hardness: 5.0
    tool_type: "pickaxe"
    tool_tier: 2
    on_interact:
      - action: give_xp
        amount: 5
      - action: run_command
        command: "say 마법 제단이 활성화되었습니다!"

  ruby_slab:
    namespace: "mypack:ruby_slab"
    texture: "ruby_slab"
    type: slab
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1

  ruby_stair:
    namespace: "mypack:ruby_stair"
    texture: "ruby_stair"
    type: stair
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1

  custom_fence:
    namespace: "mypack:custom_fence"
    texture: "custom_fence"
    type: fence
    hardness: 2.0
    tool_type: "axe"
    tool_tier: 1

  magic_leaves:
    namespace: "mypack:magic_leaves"
    texture: "magic_leaves"
    type: leaves
    no_decay: true
    hardness: 0.2
    tool_type: "shears"
    tool_tier: 1
    light_emission: 5

  crystal_block:
    namespace: "mypack:crystal_block"
    texture: "crystal_block"
    hardness: 2.0
    tool_type: "pickaxe"
    tool_tier: 1
    light_emission: 10
    drops:
      - id: "mypack:crystal_shard"
        count: 2
        chance: 1.0
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
| `follow_range` | float | `16.0` | 타겟 추적 거리 |
| `goals` | array | `[]` | AI 행동 목록 |
| `drops` | array | `[]` | 사망 드랍 아이템 |
| `spawn` | map | — | 자동 스폰 규칙 (선택) |
| `animations` | map | — | 클라이언트 애니메이션 매핑 (선택) |
| `animate` | array | — | 애니메이션 컨트롤러 상태 (선택) |

### AI Goals

`priority`는 낮을수록 우선도가 높습니다.

| 타입 | 설명 | 옵션 |
|---|---|---|
| `float` | 물 위에 뜨기 | — |
| `random_stroll` | 무작위 배회 | `speed_modifier` (기본: `1.0`) |
| `melee_attack` | 근접 공격 | `speed_modifier` (기본: `1.0`) |
| `look_at_entity` | 엔티티 바라보기 | `look_distance` (기본: `8.0`) |
| `hurt_by_target` | 피격 반격 | — |
| `nearest_attackable` | 타겟 추적 | `distance` (기본: `16.0`), `target` (기본: `"player"`) |

### 스폰 규칙 (spawn)

```yaml
entities:
  ruby_golem:
    # ...
    spawn:
      enabled: true
      biomes: [plains, forest, savanna]
      time: night      # day / night / any
      light_max: 7     # 최대 밝기 레벨 (어두울 때만 스폰)
      weight: 10       # 스폰 가중치
      min_group: 1     # 한 번에 최소 스폰 수
      max_group: 3     # 한 번에 최대 스폰 수
      max_spawned: 10  # 월드 최대 존재 수
```

### 전체 엔티티 예시

```yaml
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
    spawn:
      enabled: true
      biomes: [plains, forest]
      time: night
      light_max: 7
      weight: 10
      min_group: 1
      max_group: 2
      max_spawned: 5
    animations:
      walk:   "animation.ruby_golem.walk"
      attack: "animation.ruby_golem.attack"
    animate:
      - walk
      - { attack: "query.is_attacking" }
```

---

## 🎣 이벤트 훅 (Event Hooks)

이벤트 훅은 특정 상황에 자동으로 실행되는 액션 목록입니다.

### 아이템 훅

| 훅 | 발동 시점 |
|---|---|
| `on_use` | 아이템 우클릭 시 |
| `on_attack` | 아이템으로 엔티티 공격 시 |
| `on_eat` | 음식 아이템 섭취 완료 시 |

```yaml
items:
  fire_sword:
    namespace: "mypack:fire_sword"
    texture: "fire_sword"
    attack_points: 8
    hand_equipped: true
    add_creative_inventory: true
    on_attack:
      - action: set_on_fire
        seconds: 3
        target: target       # 공격한 대상에게 불
      - action: give_xp
        amount: 1            # 공격 시 XP 1 획득
    on_use:
      - action: play_sound
        sound: "fire.fire"
        volume: 1.0
        pitch: 1.0
```

### 블록 훅

| 훅 | 발동 시점 |
|---|---|
| `on_break` | 블록 파괴 시 (파괴한 플레이어가 source) |
| `on_place` | 블록 설치 시 (설치한 플레이어가 source) |
| `on_interact` | 블록 우클릭 시 (클릭한 플레이어가 source) |

```yaml
blocks:
  trap_block:
    namespace: "mypack:trap_block"
    texture: "trap_block"
    hardness: 1.0
    on_interact:
      - action: set_health
        amount: 4.0
        mode: remove
        target: source       # 클릭한 플레이어 피해
      - action: run_command
        command: "say 함정 발동!"
```

---

## ⚡ 이벤트 액션 (Actions)

각 훅의 `action` 목록에 사용할 수 있는 액션입니다.

### give_effect — 포션 효과 부여

```yaml
- action: give_effect
  effect: speed          # 효과 이름 (minecraft wiki 참고)
  duration: 100          # 지속 시간 (틱)
  amplifier: 1           # 강도 (0부터 시작)
  target: source         # source | target (기본: source)
```

**주요 효과 이름:** `speed` · `slowness` · `haste` · `mining_fatigue` · `strength` · `instant_health` · `instant_damage` · `jump_boost` · `regeneration` · `resistance` · `fire_resistance` · `water_breathing` · `invisibility` · `night_vision` · `hunger` · `weakness` · `poison` · `wither` · `levitation`

---

### set_health — 체력 변경

```yaml
- action: set_health
  amount: 4.0            # 체력량 (하트의 절반 단위. 4.0 = 2하트)
  mode: remove           # add | remove | set
  target: source         # source | target (기본: source)
```

| mode | 설명 |
|---|---|
| `add` | 체력 회복 (EntityRegainHealthEvent 발생) |
| `remove` | 피해 입힘 (EntityDamageEvent 발생, 방어 무시) |
| `set` | 체력을 해당 값으로 고정 |

---

### set_on_fire — 불 붙이기

```yaml
- action: set_on_fire
  seconds: 5             # 불 지속 시간 (초)
  target: target         # source | target (기본: source)
```

---

### give_xp — 경험치 조정

```yaml
- action: give_xp
  amount: 10             # XP 포인트. 음수면 차감
```

---

### give_item — 아이템 지급

```yaml
- action: give_item
  id: "mypack:ruby_gem"  # 아이템 namespace 또는 minecraft ID
  count: 1               # 지급 수량 (기본: 1)
```

---

### play_sound — 사운드 재생

```yaml
- action: play_sound
  sound: "random.orb"    # 사운드 이름
  volume: 1.0            # 음량 (기본: 1.0)
  pitch: 1.0             # 피치 (기본: 1.0)
```

---

### play_particle — 파티클 재생

```yaml
- action: play_particle
  particle: "mypack:ruby_burst"   # 파티클 이름 (particles 섹션에서 정의)
  offset_x: 0.0
  offset_y: 1.5
  offset_z: 0.0
```

---

### spawn_entity — 엔티티 소환

```yaml
- action: spawn_entity
  namespace: "mypack:fire_ball"   # entities 섹션에서 정의된 namespace
```

---

### run_command — 커맨드 실행

```yaml
- action: run_command
  command: "say {player}이 아이템을 사용했습니다!"   # {player} = 플레이어 이름
```

---

### damage — 피해 입히기

```yaml
- action: damage
  amount: 5.0            # 피해량
  target: target         # source | target (기본: target)
```

---

## 🎲 루트 테이블 (loot_tables)

블록의 `loot_table` 필드에서 이름으로 참조합니다. `drops`보다 우선합니다.

```yaml
loot_tables:
  ruby_ore_drops:
    pools:
      - rolls:
          min: 1
          max: 3
        entries:
          - id: "mypack:ruby_gem"
            weight: 80         # 높을수록 많이 나옴
            count:
              min: 1
              max: 2
            chance: 1.0        # 0.0 ~ 1.0
          - id: "minecraft:cobblestone"
            weight: 20
            count: 1
            chance: 0.5
```

블록에서 사용:
```yaml
blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    loot_table: "ruby_ore_drops"   # 위에서 정의한 이름
```

---

## 📜 레시피 (recipes)

### shaped (모양 레시피)

```yaml
recipes:
  ruby_sword:
    type: shaped
    pattern:
      - "R "
      - "R "
      - " S"
    ingredients:
      R: "mypack:ruby_gem"
      S: "minecraft:stick"
    result:
      id: "mypack:ruby_sword"
      count: 1
```

### shapeless (무작위 레시피)

```yaml
recipes:
  ruby_dust:
    type: shapeless
    ingredients:
      - "mypack:ruby_gem"
      - "mypack:ruby_gem"
    result:
      id: "mypack:ruby_dust"
      count: 3
```

### 가열 레시피

```yaml
recipes:
  ruby_smelt:
    type: furnace          # furnace / blast_furnace / smoker / campfire / soul_campfire
    input: "mypack:ruby_ore_raw"
    result:
      id: "mypack:ruby_gem"
      count: 1

  ruby_cut:
    type: stonecutter
    input: "mypack:ruby_gem"
    result:
      id: "mypack:ruby_shard"
      count: 3
```

---

## 🔊 사운드 (sounds)

리소스팩의 `sounds/` 폴더에 `.ogg` 파일을 배치하고 등록합니다.

```yaml
sounds:
  ruby_break:
    file: "ruby_break"     # sounds/ruby_break.ogg
    volume: 1.0
    pitch: 1.0
    category: block        # block / player / ambient / music / neutral / weather / record
  ruby_equip:
    file: "ruby_equip"
    volume: 0.8
    pitch: 1.2
    category: player
```

---

## ✨ 파티클 (particles)

리소스팩의 `particles/` 폴더에 파티클 JSON이 자동 생성됩니다.

```yaml
particles:
  ruby_burst:
    texture: "particles/ruby_burst"   # textures/particles/ruby_burst.png 위치
    count: 10
  magic_sparkle:
    texture: "particles/magic_sparkle"
    count: 5
```

`play_particle` 액션에서 `mypack:ruby_burst` 형식으로 참조합니다.

---

## 📋 전체 예시

```yaml
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

  health_potion:
    namespace: "mypack:health_potion"
    texture: "health_potion"
    food: true
    nutrition: 0
    saturation: 0
    can_always_eat: true
    add_creative_inventory: true
    on_eat:
      - action: set_health
        amount: 10.0
        mode: add
        target: source
      - action: give_effect
        effect: regeneration
        duration: 60
        amplifier: 1

  magic_log:
    namespace: "mypack:magic_log"
    texture: "magic_log"
    fuel: 600              # 일반 통나무의 2배
    add_creative_inventory: true

blocks:
  ruby_ore:
    namespace: "mypack:ruby_ore"
    texture: "ruby_ore"
    hardness: 3.0
    blast_resistance: 3.0
    tool_type: "pickaxe"
    tool_tier: 2
    xp_drop: { min: 3, max: 7 }
    loot_table: "ruby_ore_drops"

  magic_altar:
    namespace: "mypack:magic_altar"
    texture: "magic_altar"
    hardness: 5.0
    tool_type: "pickaxe"
    tool_tier: 2
    on_interact:
      - action: give_xp
        amount: 5
      - action: give_effect
        effect: strength
        duration: 200
        amplifier: 0

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
    spawn:
      enabled: true
      biomes: [plains, forest]
      time: night
      light_max: 7
      weight: 10
      min_group: 1
      max_group: 2
      max_spawned: 5

loot_tables:
  ruby_ore_drops:
    pools:
      - rolls: { min: 1, max: 2 }
        entries:
          - id: "mypack:ruby_gem"
            weight: 80
            count: { min: 1, max: 2 }
            chance: 1.0
          - id: "minecraft:cobblestone"
            weight: 20
            count: 1
            chance: 1.0

recipes:
  ruby_sword_recipe:
    type: shaped
    pattern:
      - "R "
      - "R "
      - " S"
    ingredients:
      R: "mypack:ruby_gem"
      S: "minecraft:stick"
    result:
      id: "mypack:fire_sword"
      count: 1

sounds:
  ruby_break:
    file: "ruby_break"
    volume: 1.0
    pitch: 1.0
    category: block

particles:
  ruby_burst:
    texture: "particles/ruby_burst"
    count: 10
```
