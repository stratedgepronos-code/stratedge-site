-- Thread X : mémorise l'ID du tweet du bet pour poster le résultat en réponse
ALTER TABLE bets ADD COLUMN x_tweet_id VARCHAR(30) NULL DEFAULT NULL;
