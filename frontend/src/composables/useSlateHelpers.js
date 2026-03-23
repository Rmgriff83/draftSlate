export function useSlateHelpers() {
  const sportIcons = {
    basketball_nba: 'mdi:basketball',
    americanfootball_nfl: 'mdi:football',
    baseball_mlb: 'mdi:baseball',
    icehockey_nhl: 'mdi:hockey-puck',
  }

  const sportIconColors = {
    basketball_nba: 'text-orange-400',
    americanfootball_nfl: 'text-amber-600',
    baseball_mlb: 'text-red-400',
    icehockey_nhl: 'text-sky-400',
  }

  const typeLabels = {
    moneyline: 'ML',
    spread: 'Spread',
    total: 'O/U',
    player_prop: 'Prop',
  }

  const typeBadgeClasses = {
    moneyline: 'bg-blue-500/20 text-blue-400',
    spread: 'bg-purple-500/20 text-purple-400',
    total: 'bg-orange-500/20 text-orange-400',
    player_prop: 'bg-green-500/20 text-green-400',
  }

  const outcomeClasses = {
    hit: 'border-l-4 border-ds-green shadow-ds-hit',
    miss: 'border-l-4 border-ds-red shadow-ds-miss',
    void: 'border-l-4 border-gray-500 opacity-60',
    push: 'border-l-4 border-yellow-500',
    pending: '',
  }

  const outcomeBadgeClasses = {
    hit: 'bg-green-500/20 text-green-400',
    miss: 'bg-red-500/20 text-red-400',
    void: 'bg-gray-500/20 text-gray-400',
    push: 'bg-yellow-500/20 text-yellow-400',
    pending: 'bg-gray-600/20 text-gray-400',
  }

  function outcomeLabel(outcome) {
    const labels = {
      hit: 'HIT',
      miss: 'MISS',
      void: 'VOID',
      push: 'PUSH',
      pending: 'PENDING',
    }
    return labels[outcome] || 'PENDING'
  }

  function formatOdds(odds) {
    if (odds === null || odds === undefined) return '--'
    return odds > 0 ? `+${odds}` : `${odds}`
  }

  function oddsColor(odds) {
    if (odds === null || odds === undefined) return 'text-gray-400'
    return odds > 0 ? 'text-green-400' : 'text-red-400'
  }

  function formatDrift(drift) {
    if (drift === null || drift === undefined || drift === 0) return null
    const sign = drift > 0 ? '+' : ''
    return `${sign}${drift}`
  }

  function driftColor(drift) {
    if (!drift || drift === 0) return 'text-gray-400'
    return drift > 0 ? 'text-green-400' : 'text-red-400'
  }

  function formatGameTime(gameTime) {
    if (!gameTime) return null
    const d = new Date(gameTime)
    const now = new Date()
    const isToday = d.toDateString() === now.toDateString()
    const tomorrow = new Date(now)
    tomorrow.setDate(tomorrow.getDate() + 1)
    const isTomorrow = d.toDateString() === tomorrow.toDateString()
    const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
    if (isToday) return `Today ${time}`
    if (isTomorrow) return `Tomorrow ${time}`
    const month = d.toLocaleDateString([], { month: 'short', day: 'numeric' })
    return `${month} ${time}`
  }

  function isLive(pick) {
    const ps = pick?.pick_selection
    if (!ps) return false
    if (ps.outcome !== 'pending') return false
    if (!ps.game_time) return false
    return new Date(ps.game_time) <= new Date()
  }

  function liveScore(pick) {
    const rd = pick?.pick_selection?.result_data
    if (!rd) return null
    if (rd.home_score == null && rd.away_score == null) return null
    return {
      home: rd.home_score ?? 0,
      away: rd.away_score ?? 0,
      homeTeam: rd.home_team || pick.pick_selection.home_team || 'HOME',
      awayTeam: rd.away_team || pick.pick_selection.away_team || 'AWAY',
      period: rd.period || null,
      gameStatus: rd.game_status || null,
    }
  }

  /**
   * Compute progress toward a pick's target.
   * Returns { label, detail, trend, progress } or null.
   */
  function pickProgress(pick) {
    const ps = pick?.pick_selection
    const rd = ps?.result_data
    if (!rd || !ps) return null

    const type = ps.pick_type
    const homeScore = rd.home_score ?? 0
    const awayScore = rd.away_score ?? 0
    const homeTeam = rd.home_team || ps.home_team || ''
    const awayTeam = rd.away_team || ps.away_team || ''
    const desc = ps.description || ''

    switch (type) {
      case 'moneyline': {
        const teamMatch = desc.match(/^(.+?)\s+ML/i)
        const pickedTeam = teamMatch?.[1]?.trim()
        const isHome = pickedTeam === homeTeam || homeTeam.includes(pickedTeam || '---')
        const pickedScore = isHome ? homeScore : awayScore
        const otherScore = isHome ? awayScore : homeScore
        const margin = pickedScore - otherScore
        return {
          label: margin > 0 ? `+${margin}` : margin < 0 ? `${margin}` : 'Tied',
          detail: `${awayScore} - ${homeScore}`,
          trend: margin > 0 ? 'positive' : margin < 0 ? 'negative' : 'neutral',
        }
      }
      case 'spread': {
        const spreadMatch = desc.match(/^(.+?)\s+([+-]?[\d.]+)\s/i)
        const pickedTeam = spreadMatch?.[1]?.trim()
        const spreadLine = parseFloat(spreadMatch?.[2])
        if (isNaN(spreadLine)) return null
        const isHome = pickedTeam === homeTeam || homeTeam.includes(pickedTeam || '---')
        const margin = isHome ? homeScore - awayScore : awayScore - homeScore
        const covering = margin + spreadLine
        return {
          label: covering > 0 ? `+${covering.toFixed(1)}` : covering.toFixed(1),
          detail: `Margin ${margin > 0 ? '+' : ''}${margin} / Line ${spreadLine > 0 ? '+' : ''}${spreadLine}`,
          trend: covering > 0 ? 'positive' : covering < 0 ? 'negative' : 'neutral',
        }
      }
      case 'total': {
        const totalMatch = desc.match(/^(Over|Under)\s+([\d.]+)/i)
        const side = totalMatch?.[1]?.toLowerCase()
        const line = parseFloat(totalMatch?.[2])
        if (isNaN(line)) return null
        const currentTotal = homeScore + awayScore
        const isOver = side === 'over'
        const remaining = isOver ? line - currentTotal : currentTotal - line
        return {
          label: `${currentTotal} / ${line}`,
          detail: remaining > 0
            ? `Need ${remaining.toFixed(1)} more`
            : (isOver ? 'Cleared' : 'Under'),
          trend: (isOver ? remaining <= 0 : remaining >= 0) ? 'positive' : 'neutral',
        }
      }
      case 'player_prop': {
        // Use current_stat from result_data if available
        const currentStat = rd.current_stat
        const statLabel = rd.stat_label
        if (currentStat == null) {
          // No player stats available, fall back to game score
          return {
            label: `${awayScore} - ${homeScore}`,
            detail: rd.game_status || null,
            trend: 'neutral',
          }
        }
        // Extract the line from description: "Player Over/Under X.X stat"
        const propMatch = desc.match(/(?:Over|Under)\s+([\d.]+)/i)
        const line = propMatch ? parseFloat(propMatch[1]) : null
        const sideMatch = desc.match(/(Over|Under)/i)
        const side = sideMatch?.[1]?.toLowerCase()

        if (line != null) {
          const remaining = line - currentStat
          const isOver = side === 'over'
          const hit = isOver ? currentStat > line : currentStat < line
          const close = isOver ? remaining <= 0 : remaining >= 0
          return {
            label: `${currentStat} / ${line}`,
            detail: `${statLabel || ''}${rd.game_status ? ' · ' + rd.game_status : ''}`,
            trend: close ? 'positive' : (isOver && remaining <= line * 0.3 ? 'neutral' : 'negative'),
            progress: line > 0 ? Math.min(currentStat / line, 1.5) : 0,
          }
        }

        return {
          label: `${currentStat} ${statLabel || ''}`,
          detail: rd.game_status || null,
          trend: 'neutral',
        }
      }
      default:
        return null
    }
  }

  /**
   * Return a short result summary string for a graded pick.
   * e.g. "13 / 13.5 points" for props, "75-88" for game lines.
   */
  function pickResultText(pick) {
    const ps = pick?.pick_selection
    if (!ps) return null
    const outcome = ps.outcome
    if (!outcome || outcome === 'pending') return null
    const rd = ps.result_data
    if (!rd) return null
    const type = ps.pick_type
    const desc = ps.description || ''

    if (type === 'player_prop') {
      const stat = rd.current_stat
      if (stat == null) return null
      const propMatch = desc.match(/(?:Over|Under)\s+([\d.]+)/i)
      const line = propMatch ? parseFloat(propMatch[1]) : null
      const label = rd.stat_label || ''
      return line != null ? `${stat} / ${line} ${label}` : `${stat} ${label}`
    }

    const home = rd.home_score
    const away = rd.away_score
    if (home == null || away == null) return null

    if (type === 'total') {
      const totalMatch = desc.match(/(?:Over|Under)\s+([\d.]+)/i)
      const line = totalMatch ? parseFloat(totalMatch[1]) : null
      const total = home + away
      return line != null ? `${away}-${home} · ${total} / ${line}` : `${away}-${home}`
    }

    if (type === 'spread') {
      const spreadMatch = desc.match(/^(.+?)\s+([+-]?[\d.]+)\s/i)
      const pickedTeam = spreadMatch?.[1]?.trim()
      const spreadLine = parseFloat(spreadMatch?.[2])
      if (pickedTeam && !isNaN(spreadLine)) {
        const homeTeam = rd.home_team || ps.home_team || ''
        const isHome = pickedTeam === homeTeam || homeTeam.includes(pickedTeam)
        const margin = isHome ? home - away : away - home
        const adjusted = margin + spreadLine
        return `${away}-${home} · ${adjusted > 0 ? '+' : ''}${adjusted.toFixed(1)}`
      }
    }

    return `${away}-${home}`
  }

  return {
    sportIcons,
    sportIconColors,
    typeLabels,
    typeBadgeClasses,
    outcomeClasses,
    outcomeBadgeClasses,
    outcomeLabel,
    formatOdds,
    oddsColor,
    formatDrift,
    driftColor,
    formatGameTime,
    isLive,
    liveScore,
    pickProgress,
    pickResultText,
  }
}
