length :: [*] -> num
length [] = 0
length (x:l) = 1 + length l 

oas :: [*] -> *
oas [] = error "list can\'t be empty"
oas [x] = x
oas (x:l) = oas l

