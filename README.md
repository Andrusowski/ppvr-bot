https://twitter.com/bahamete/status/919625209619079170

this is why

MySQL:
- top pp list
    SELECT posts.player_id, players.name, SUM(posts.score) AS score
    FROM posts INNER JOIN players ON players.id = posts.player_id
    GROUP BY posts.player_id
    ORDER BY score DESC
