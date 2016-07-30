// usage | cardFilter({ member: 'admin'})

app.filter('cardFilter', function() {
    return function(cards, rules) {
		var _result = {};
		angular.forEach(cards, function(card){
			var _card = card;
			angular.some(rules, function(rule, condition) {
				if(_card[rule]===condition) {
					_result.push(_card);
				}
			});
		});
		return result;
    };
});