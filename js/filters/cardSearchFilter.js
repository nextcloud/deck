app.filter('cardSearchFilter', function() {
	return function(cards, searchString) {
		var _result = {};
		var rules = {
			title: searchString,
			//owner: searchString,
		};
		angular.forEach(cards, function(card){
			var _card = card;
			Object.keys(rules).some(function(rule) {
				if(_card[rule].search(rules[rule])>=0) {
					_result[_card.id] = _card;
				}
			});
		});

		var arrayResult = $.map(_result, function(value, index) {
			return [value];
		});

		return arrayResult;
	};
});